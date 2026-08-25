<?php

namespace RRZE\Answers\Common\API;

use RRZE\Answers\Common\Sync\RemoteEntryFetcher;
use RRZE\Answers\Common\Sync\SynchronizedEntryStore;
use RRZE\Answers\Common\Sync\SyncOperationJournal;

defined('ABSPATH') || exit;

/**
 * Coordinates remote Answers synchronization and exposes its legacy API.
 *
 * Remote entry retrieval and validation belong to RemoteEntryFetcher. Local
 * WordPress persistence belongs to SynchronizedEntryStore. This facade keeps
 * the public methods used by cron, settings, and existing integrations stable
 * while enforcing the order of the synchronization transaction.
 */
class SyncAPI
{
    /** @var array<int, array<string, mixed>> Legacy category hierarchy state. */
    private array $allCategories = [];

    private RemoteEntryFetcher $entryFetcher;

    private SynchronizedEntryStore $entryStore;

    /**
     * Create the synchronization facade.
     *
     * Optional collaborators make the boundary independently testable without
     * changing the historic no-argument construction used by the plugin.
     */
    public function __construct(
        ?RemoteEntryFetcher $entryFetcher = null,
        ?SynchronizedEntryStore $entryStore = null
    ) {
        $this->entryFetcher = $entryFetcher ?? new RemoteEntryFetcher();
        $this->entryStore = $entryStore ?? new SynchronizedEntryStore();
    }

    /**
     * Fetch the remotely available taxonomy terms as a slug-to-name map.
     *
     * @param mixed $url    Source site URL.
     * @param mixed $field  REST taxonomy endpoint name.
     * @param mixed $filter Optional taxonomy slug filter, passed by reference
     *                      for backwards compatibility.
     * @return array<string, string>|\WP_Error
     */
    public function getTaxonomies($url, $field, &$filter)
    {
        $cacheKey = 'rrze_answers_tax_v2_' . md5($url . '|' . $field . '|' . (string) $filter);
        $cachedTaxonomies = get_transient($cacheKey);

        if ($cachedTaxonomies !== false) {
            return $cachedTaxonomies;
        }

        $taxonomies = [];
        $slugFilter = $filter ? '&slug=' . $filter : '';
        $currentPage = 1;

        try {
            do {
                $response = $this->remoteGet(
                    $url . '/' . ENDPOINT . $field . '?page=' . $currentPage . $slugFilter
                );

                if (is_wp_error($response)) {
                    break;
                }

                $statusCode = (int) wp_remote_retrieve_response_code($response);
                if ($statusCode === 403) {
                    return new \WP_Error(
                        'remote_forbidden',
                        __('Import not allowed by source site.', 'rrze-answers')
                    );
                }

                if ($statusCode !== 200) {
                    break;
                }

                $entries = json_decode(wp_remote_retrieve_body($response), true);
                if (empty($entries)) {
                    break;
                }

                foreach ($entries as $entry) {
                    if (!isset($entry['source']) || $entry['source'] !== 'website') {
                        continue;
                    }

                    $name = $entry['name'] ?? null;
                    $slug = $entry['slug'] ?? null;
                    if (!$name || !$slug) {
                        continue;
                    }

                    $taxonomies[$slug] = $name;
                }

                $currentPage++;
            } while (true);

            set_transient($cacheKey, $taxonomies, HOUR_IN_SECONDS);

            return $taxonomies;
        } catch (\Throwable $exception) {
            return new \WP_Error(
                'getTaxonomies_error',
                __('Error in getTaxonomies().', 'rrze-answers')
            );
        }
    }

    /**
     * Delete terms belonging to an explicitly removed source.
     *
     * @return void|\WP_Error
     */
    public function deleteTaxonomies($identifier, $field)
    {
        return $this->entryStore->deleteTaxonomies((string) $identifier, (string) $field);
    }

    /**
     * Delete category terms belonging to an explicitly removed source.
     *
     * @return void|\WP_Error
     */
    public function deleteCategories($identifier, $type)
    {
        return $this->deleteTaxonomies($identifier, 'rrze_' . $type . '_category');
    }

    /**
     * Delete tag terms belonging to an explicitly removed source.
     *
     * @return void|\WP_Error
     */
    public function deleteTags($identifier, $type)
    {
        return $this->deleteTaxonomies($identifier, 'rrze_' . $type . '_tag');
    }

    /**
     * Resolve remote category names to local term IDs.
     *
     * @param string[] $categoryNames
     * @return int[]|\WP_Error
     */
    protected function setCategories(
        array $categoryNames,
        string $sourceIdentifier,
        string $contentType,
        ?SyncOperationJournal $operationJournal = null
    ) {
        return $this->entryStore->resolveCategoryIds(
            $categoryNames,
            $sourceIdentifier,
            $contentType,
            $operationJournal
        );
    }

    /**
     * Flatten legacy category data into the provided hierarchy.
     *
     * @param array<mixed> $categories Legacy mutable category data.
     * @param array<mixed> $hierarchy  Legacy mutable hierarchy output.
     */
    public function sortAllCats(&$categories, &$hierarchy)
    {
        foreach ($categories as $termId => $details) {
            $hierarchy[$termId]['slug'] = $details['slug'];
            $hierarchy[$termId]['name'] = $details['name'];

            if ($details['parentID']) {
                $parentId = $details['parentID'];
                $hierarchy[$parentId][$termId]['slug'] = $details['slug'];
                $hierarchy[$parentId][$termId]['name'] = $details['name'];
                unset($categories[$parentId]);
            }
        }

        if ($categories !== []) {
            $this->sortAllCats($categories, $hierarchy);
        }
    }

    /**
     * Build the historic nested category object structure.
     *
     * @param array<int, \WP_Term> $categories Remaining categories to place.
     * @param array<mixed>         $hierarchy  Mutable legacy hierarchy output.
     * @return void|\WP_Error
     */
    public function sortCats(array &$categories, array &$hierarchy, $parentID = 0, $prefix = '')
    {
        try {
            $prefix .= $parentID ? '-' : '';

            foreach ($categories as $index => $category) {
                if ($category->parent == $parentID) {
                    $hierarchy[$category->term_id] = $category;
                    unset($categories[$index]);
                }

                $this->allCategories[$category->term_id]['parentID'] = $category->parent;
                $this->allCategories[$category->term_id]['slug'] = $category->slug;
                $this->allCategories[$category->term_id]['name'] = str_replace(
                    '~',
                    '&nbsp;',
                    str_pad(ltrim($prefix . ' ' . $category->name), 100, '~')
                );
            }

            foreach ($hierarchy as $topCategory) {
                $topCategory->children = [];
                $this->sortCats(
                    $categories,
                    $topCategory->children,
                    $topCategory->term_id,
                    $prefix
                );
            }

            if (!$categories) {
                foreach ($this->allCategories as $termId => $details) {
                    if ($details['parentID']) {
                        $this->allCategories[$details['parentID']]['children'][$termId]
                            = $this->allCategories[$termId];
                    }
                }
            }
        } catch (\Throwable $exception) {
            return new \WP_Error('sortCats_error', __('Error in sortCats().', 'rrze-answers'));
        }
    }

    /**
     * Remove child categories from the legacy category hierarchy root.
     */
    public function cleanCats(): void
    {
        foreach ($this->allCategories as $termId => $details) {
            if ($details['parentID']) {
                unset($this->allCategories[$termId]);
            }
        }
    }

    /**
     * Convert a legacy category hierarchy to a slug-to-name map.
     *
     * @param array<mixed> $categories Mutable legacy hierarchy input.
     * @param array<mixed> $slugNameMap Mutable slug-to-name output.
     */
    public function getSlugNameCats(&$categories, &$slugNameMap): void
    {
        foreach ($categories as $index => $category) {
            $slugNameMap[$category['slug']] = $category['name'];

            if (isset($category['children'])) {
                $this->getSlugNameCats($category['children'], $slugNameMap);
            }

            unset($categories[$index]);
        }
    }

    /**
     * Permanently delete entries after their source was explicitly removed.
     */
    public function deleteEntries($identifier, $type): int
    {
        return $this->entryStore->deleteEntries((string) $identifier, (string) $type);
    }

    /**
     * Convert relative URLs in synchronized HTML to absolute source URLs.
     *
     * @return string|\WP_Error
     */
    protected function absoluteUrl($content, $sourceUrl)
    {
        return $this->entryFetcher->normalizeContentUrls(
            (string) $content,
            (string) $sourceUrl
        );
    }

    /**
     * Fetch and validate the complete remote collection before local writes.
     *
     * @return array<int|string, array<string, mixed>>|\WP_Error
     */
    protected function getEntries(
        string $sourceUrl,
        string $selectedCategories,
        string $contentType
    ) {
        return $this->entryFetcher->fetch($sourceUrl, $selectedCategories, $contentType);
    }

    /**
     * Resolve a comma-separated list of remote tag names to local term IDs.
     *
     * @param mixed $terms
     * @return int[]|\WP_Error
     */
    public function setTags(
        $terms,
        $sourceIdentifier,
        $contentType,
        ?SyncOperationJournal $operationJournal = null
    ) {
        return $this->entryStore->resolveTagIds(
            $terms,
            (string) $sourceIdentifier,
            (string) $contentType,
            $operationJournal
        );
    }

    /**
     * Return locally stored entries indexed by their remote ID.
     *
     * The method name and historic array keys remain stable for backwards
     * compatibility with existing integrations.
     *
     * @return array<int|string, array{postID: int, remoteChanged: mixed}>|\WP_Error
     */
    public function getEntriesRemoteIDs($sourceIdentifier, $contentType)
    {
        return $this->entryStore->getEntriesByRemoteId(
            (string) $sourceIdentifier,
            (string) $contentType
        );
    }

    /**
     * Synchronize one complete FAQ or glossary collection from one source.
     *
     * The transaction order is intentional: fetch and validate all pages,
     * reconcile terms and posts, then move obsolete posts to Trash. The journal
     * compensates completed local writes when a later operation fails.
     *
     * @return array{iNew: int, iUpdated: int, iDeleted: int, URLhasSlider: string[]}|\WP_Error
     */
    public function setEntries($contentType, $sourceIdentifier, $selectedCategories, $sourceUrl)
    {
        $operationJournal = new SyncOperationJournal();

        try {
            $newCount = 0;
            $updatedCount = 0;
            $sliderUrls = [];
            $contentType = (string) $contentType;
            $sourceIdentifier = (string) $sourceIdentifier;
            $postType = 'rrze_' . $contentType;
            $tagTaxonomy = $postType . '_tag';
            $categoryTaxonomy = $postType . '_category';

            $remainingLocalEntries = $this->getEntriesRemoteIDs(
                $sourceIdentifier,
                $contentType
            );
            if (is_wp_error($remainingLocalEntries)) {
                return $remainingLocalEntries;
            }

            $remoteEntries = $this->getEntries(
                (string) $sourceUrl,
                (string) $selectedCategories,
                $contentType
            );
            if (is_wp_error($remoteEntries)) {
                return $remoteEntries;
            }

            if ($remoteEntries === [] && $remainingLocalEntries !== []) {
                return new \WP_Error(
                    'remote_empty_response',
                    __('The source site returned no entries. Existing synchronized entries were preserved.', 'rrze-answers')
                );
            }

            foreach ($remoteEntries as $remoteEntry) {
                $remoteId = $remoteEntry['remoteID'];

                if ($remoteEntry['URLhasSlider']) {
                    $sliderUrls[] = $remoteEntry['URLhasSlider'];
                    unset($remainingLocalEntries[$remoteId]);
                    continue;
                }

                $localEntry = $remainingLocalEntries[$remoteId] ?? null;
                if (
                    $localEntry !== null
                    && $localEntry['remoteChanged'] >= $remoteEntry['remoteChanged']
                ) {
                    unset($remainingLocalEntries[$remoteId]);
                    continue;
                }

                if ($localEntry !== null) {
                    $snapshotError = $operationJournal->capturePostBeforeUpdate(
                        $localEntry['postID'],
                        [$categoryTaxonomy, $tagTaxonomy]
                    );
                    if (is_wp_error($snapshotError)) {
                        return $this->rollbackAfterFailure($snapshotError, $operationJournal);
                    }
                }

                $tagIds = $this->setTags(
                    $remoteEntry[$tagTaxonomy],
                    $sourceIdentifier,
                    $contentType,
                    $operationJournal
                );
                if (is_wp_error($tagIds)) {
                    return $this->rollbackAfterFailure($tagIds, $operationJournal);
                }

                $categoryIds = $this->setCategories(
                    $remoteEntry[$categoryTaxonomy],
                    $sourceIdentifier,
                    $contentType,
                    $operationJournal
                );
                if (is_wp_error($categoryIds)) {
                    return $this->rollbackAfterFailure($categoryIds, $operationJournal);
                }

                if ($localEntry !== null) {
                    $updatedPost = $this->entryStore->updatePost(
                        $localEntry['postID'],
                        $remoteEntry,
                        $sourceIdentifier,
                        $categoryTaxonomy,
                        $tagTaxonomy,
                        $categoryIds,
                        $tagIds
                    );
                    if (is_wp_error($updatedPost)) {
                        return $this->rollbackAfterFailure($updatedPost, $operationJournal);
                    }

                    $updatedCount++;
                    unset($remainingLocalEntries[$remoteId]);
                    continue;
                }

                $insertedPost = $this->entryStore->insertPost(
                    $postType,
                    $remoteEntry,
                    $sourceIdentifier,
                    $categoryTaxonomy,
                    $tagTaxonomy,
                    $categoryIds,
                    $tagIds
                );
                if (is_wp_error($insertedPost)) {
                    return $this->rollbackAfterFailure($insertedPost, $operationJournal);
                }

                $operationJournal->recordInsertedPost($insertedPost);
                $newCount++;
            }

            $deletedCount = $this->entryStore->trashObsoleteEntries(
                $remainingLocalEntries,
                $operationJournal
            );
            if (is_wp_error($deletedCount)) {
                return $this->rollbackAfterFailure($deletedCount, $operationJournal);
            }

            return [
                'iNew' => $newCount,
                'iUpdated' => $updatedCount,
                'iDeleted' => $deletedCount,
                'URLhasSlider' => $sliderUrls,
            ];
        } catch (\Throwable $exception) {
            return $this->rollbackAfterFailure(
                new \WP_Error('setFAQ_error', __('Error in setEntries().', 'rrze-answers')),
                $operationJournal
            );
        }
    }

    /**
     * Roll back recorded writes and retain the original synchronization error.
     */
    private function rollbackAfterFailure(
        \WP_Error $syncError,
        SyncOperationJournal $operationJournal
    ): \WP_Error {
        $rollbackError = $operationJournal->rollback();

        if ($rollbackError !== null) {
            $syncError->add(
                'sync_rollback_failed',
                __('Synchronization failed and some local changes could not be rolled back.', 'rrze-answers'),
                ['rollbackErrors' => $rollbackError->get_error_messages()]
            );
        }

        return $syncError;
    }

    /**
     * Perform an optionally cached WordPress HTTP request.
     *
     * This helper remains for taxonomy discovery. Entry synchronization uses
     * the uncached RemoteEntryFetcher because deletion requires a fresh view.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>|\WP_Error
     */
    private function remoteGet(
        string $url,
        array $arguments = [],
        bool $safe = true,
        bool $useCache = true
    ) {
        $cacheKey = 'rrze_remote_' . md5($url);
        $cachedResponse = $useCache ? get_transient($cacheKey) : false;

        if ($cachedResponse !== false) {
            return $cachedResponse;
        }

        try {
            if ($arguments === []) {
                $arguments = [
                    'sslverify' => !(defined('WP_DEBUG') && WP_DEBUG),
                    'timeout' => 5,
                ];
            }

            $response = $safe
                ? wp_safe_remote_get($url, $arguments)
                : wp_remote_get($url, $arguments);

            if (
                $useCache
                && !is_wp_error($response)
                && (int) wp_remote_retrieve_response_code($response) === 200
            ) {
                set_transient($cacheKey, $response, 10 * MINUTE_IN_SECONDS);
            }

            return $response;
        } catch (\Throwable $exception) {
            return new \WP_Error('remoteGet_error', __('Error in remoteGet().', 'rrze-answers'));
        }
    }

    /**
     * Return configured synchronization sources sorted by identifier.
     *
     * @return array<string, string>
     */
    public function getDomains(): array
    {
        $domains = [];
        $options = get_option('rrze-answers');

        if (isset($options['registeredDomains'])) {
            foreach ($options['registeredDomains'] as $identifier => $url) {
                $domains[$identifier] = $url;
            }
        }

        asort($domains);

        return $domains;
    }

    /**
     * Validate a new source URL and identifier against configured sources.
     *
     * @param mixed $identifier
     * @param mixed $url
     * @param mixed $domains
     * @return array<string, mixed>
     */
    public function checkDomain($identifier, $url, $domains): array
    {
        $result = ['status' => false, 'msg' => ''];

        if (in_array($url, $domains)) {
            $result['msg'] = $url . ' ' . __('is already in use.', 'rrze-answers');
            return $result;
        }

        if (array_key_exists($identifier, $domains)) {
            $result['msg'] = $identifier . ' ' . __('is already in use.', 'rrze-answers');
            return $result;
        }

        foreach (['faq', 'synonym', 'glossary'] as $subEndpoint) {
            $response = wp_remote_get($url . '/' . ENDPOINT . $subEndpoint . '?per_page=1');
            $statusCode = wp_remote_retrieve_response_code($response);

            if ($statusCode != '200') {
                $result['msg'] = $url . ' ' . __('is not valid.', 'rrze-answers');
                continue;
            }

            $content = json_decode(wp_remote_retrieve_body($response), true);
            if (!$content) {
                $result['ret'] = $url . ' ' . __(' does not support this plugin.', 'rrze-answers');
                continue;
            }

            $result['status'] = true;
            break;
        }

        return $result;
    }
}
