<?php

namespace RRZE\Answers\Common\API;

use RRZE\Answers\Common\CustomException;
use RRZE\Answers\Common\Sync\SyncOperationJournal;

defined('ABSPATH') || exit;


/**
 * Fetch and reconcile synchronized FAQ and glossary content.
 *
 * The class still contains older taxonomy-discovery helpers, but the entry
 * synchronization path follows a strict safety boundary: a complete remote
 * snapshot is validated before local writes begin, and obsolete local entries
 * are moved to Trash only after every term and post write succeeds.
 */
class SyncAPI
{
    private $aAllCats = [];


    public function __construct()
    {
    }

    public function getTaxonomies($url, $field, &$filter)
    {
        $cacheKey = 'rrze_answers_tax_v2_' . md5($url . '|' . $field . '|' . (string) $filter);
        $cached = get_transient($cacheKey);

        if (!empty($cached)) {
            return $cached;
        }

        // Return flat map: slug => name
        $aRet = [];
        $slug = ($filter ? '&slug=' . $filter : '');
        $page = 1;

        try {
            do {
                $request = $this->remoteGet($url . '/' . ENDPOINT . $field . '?page=' . $page . $slug);

                if (is_wp_error($request)) {
                    break;
                }

                $status_code = wp_remote_retrieve_response_code($request);

                if ($status_code === 403) {
                    return new \WP_Error('remote_forbidden', __('Import not allowed by source site.', 'rrze-answers'));
                }

                if ($status_code !== 200) {
                    break;
                }

                $entries = json_decode(wp_remote_retrieve_body($request), true);

                if (empty($entries)) {
                    break;
                }

                foreach ($entries as $entry) {
                    if (!isset($entry['source']) || $entry['source'] !== 'website') {
                        continue;
                    }

                    $name = $entry['name'] ?? null;
                    $term_slug = $entry['slug'] ?? null;
                    if (!$name || !$term_slug) {
                        continue;
                    }

                    $aRet[$term_slug] = $name;
                }

                $page++;
            } while (true);

            // Cache the result for 1 hour
            set_transient($cacheKey, $aRet, HOUR_IN_SECONDS);

            return $aRet;
        } catch (CustomException $e) {
            return new \WP_Error('getTaxonomies_error', __('Error in getTaxonomies().', 'rrze-answers'));
        }
    }

    public function deleteTaxonomies($identifier, $field)
    {
        try {
            $args = array(
                'hide_empty' => false,
                'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    array(
                        'key' => 'source',
                        'value' => $identifier,
                        'compare' => '=',
                    ),
                ),
                'taxonomy' => $field,
                'fields' => 'ids',
            );
            $terms = get_terms($args);
            foreach ($terms as $ID) {
                wp_delete_term($ID, $field);
            }
        } catch (CustomException $e) {
            return new \WP_Error('deleteTaxonomies_error', __('Error in deleteTaxonomies().', 'rrze-answers'));
        }
    }

    public function deleteCategories($identifier, $type)
    {
        $this->deleteTaxonomies($identifier, 'rrze_' . $type . '_category');
    }

    public function deleteTags($identifier, $type)
    {
        $this->deleteTaxonomies($identifier, 'rrze_' . $type . '_tag');
    }

    /**
     * Resolve remote category names to local term IDs.
     *
     * A term write failure must abort synchronization before obsolete posts are
     * deleted. Returning the original WP_Error preserves the useful error code
     * and message for the sync log and admin notice.
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
        $termIds = [];
        $taxonomy = 'rrze_' . $contentType . '_category';

        try {
            foreach ($categoryNames as $categoryName) {
                $term = term_exists($categoryName, $taxonomy);
                $isNewTerm = !$term;

                if ($isNewTerm) {
                    $term = wp_insert_term($categoryName, $taxonomy);
                }

                if (is_wp_error($term)) {
                    return $term;
                }

                $termId = (int) $term['term_id'];

                if ($termId <= 0) {
                    return new \WP_Error(
                        'sync_category_invalid',
                        __('A synchronized category could not be resolved. No entries were deleted.', 'rrze-answers')
                    );
                }

                $termIds[] = $termId;

                if ($isNewTerm) {
                    update_term_meta($termId, 'source', $sourceIdentifier);
                    $operationJournal?->recordCreatedTerm($termId, $taxonomy);
                }
            }

            return $termIds;
        } catch (\Throwable $exception) {
            return new \WP_Error('setCategories_error', __('Error in setCategories().', 'rrze-answers'));
        }
    }

    public function sortAllCats(&$cats, &$into)
    {
        foreach ($cats as $ID => $aDetails) {
            $into[$ID]['slug'] = $aDetails['slug'];
            $into[$ID]['name'] = $aDetails['name'];

            if ($aDetails['parentID']) {
                $parentID = $aDetails['parentID'];
                $into[$parentID][$ID]['slug'] = $aDetails['slug'];
                $into[$parentID][$ID]['name'] = $aDetails['name'];
                unset($cats[$parentID]);
            }
        }

        $this->sortAllCats($cats, $into);
    }

    public function sortCats(array &$cats, array &$into, $parentID = 0, $prefix = '')
    {
        try {
            $prefix .= ($parentID ? '-' : '');
            foreach ($cats as $i => $cat) {
                if ($cat->parent == $parentID) {
                    $into[$cat->term_id] = $cat;
                    unset($cats[$i]);
                }
                $this->aAllCats[$cat->term_id]['parentID'] = $cat->parent;
                $this->aAllCats[$cat->term_id]['slug'] = $cat->slug;
                $this->aAllCats[$cat->term_id]['name'] = str_replace('~', '&nbsp;', str_pad(ltrim($prefix . ' ' . $cat->name), 100, '~'));
            }
            foreach ($into as $topCat) {
                $topCat->children = [];
                $this->sortCats($cats, $topCat->children, $topCat->term_id, $prefix);
            }
            if (!$cats) {
                foreach ($this->aAllCats as $ID => $aDetails) {
                    if ($aDetails['parentID']) {
                        $this->aAllCats[$aDetails['parentID']]['children'][$ID] = $this->aAllCats[$ID];
                    }
                }
            }
        } catch (CustomException $e) {
            return new \WP_Error('sortCats_error', __('Error in sortCats().', 'rrze-answers'));
        }
    }

    public function cleanCats()
    {
        foreach ($this->aAllCats as $ID => $aDetails) {
            if ($aDetails['parentID']) {
                unset($this->aAllCats[$ID]);
            }
        }
    }

    public function getSlugNameCats(&$cats, &$into)
    {
        foreach ($cats as $i => $cat) {
            $into[$cat['slug']] = $cat['name'];
            if (isset($cat['children'])) {
                $this->getSlugNameCats($cat['children'], $into);
            }
            unset($cats[$i]);
        }
    }

    // public function getCategories($identifier, $url, $type, $categories = '')
    // {
    //     $aRet = [];
    //     $field = 'rrze_' . $type . '_category';
    //     $aCategories = $this->getTaxonomies($url, $field, $categories);
    //     $this->setCategories($aCategories, $identifier, $type);

    //     $categories = get_terms(array(
    //         'taxonomy' => $field,
    //         'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    //             array(
    //                 'key' => 'source',
    //                 'value' => $identifier,
    //             )
    //         ),
    //         'hide_empty' => false,
    //     ));
    //     $categoryHierarchy = [];
    //     $this->sortCats($categories, $categoryHierarchy);
    //     $this->cleanCats();
    //     $this->getSlugNameCats($this->aAllCats, $aRet);
    //     return $aRet;
    // }

    public function deleteEntries($identifier, $type)
    {
        // deletes all Entries by url
        $iDel = 0;
        $allEntries = get_posts(array('post_type' => 'rrze_' . $type, 'meta_key' => 'source', 'meta_value' => $identifier, 'numberposts' => -1)); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        foreach ($allEntries as $entry) {
            wp_delete_post($entry->ID, true);
            $iDel++;
        }
        return $iDel;
    }

    protected function absoluteUrl($txt, $baseUrl)
    {
        try {
            // converts relative URLs to absolute ones
            $needles = array('href="', 'src="', 'background="');
            $newTxt = '';
            if (substr($baseUrl, -1) != '/') {
                $baseUrl .= '/';
            }
            $newBaseUrl = $baseUrl;
            $baseUrlParts = wp_parse_url($baseUrl);
            foreach ($needles as $needle) {
                while ($pos = strpos($txt, $needle)) {
                    $pos += strlen($needle);
                    if (substr($txt, $pos, 7) != 'http://' && substr($txt, $pos, 8) != 'https://' && substr($txt, $pos, 6) != 'ftp://' && substr($txt, $pos, 7) != 'mailto:') {
                        if (substr($txt, $pos, 1) == '/') {
                            $newBaseUrl = $baseUrlParts['scheme'] . '://' . $baseUrlParts['host'];
                        }
                        $newTxt .= substr($txt, 0, $pos) . $newBaseUrl;
                    } else {
                        $newTxt .= substr($txt, 0, $pos);
                    }
                    $txt = substr($txt, $pos);
                }
                $txt = $newTxt . $txt;
                $newTxt = '';
            }
            // convert all elements of srcset, too
            $needle = 'srcset="';
            while ($pos = strpos($txt, $needle, $pos)) {
                $pos += strlen($needle);
                $len = strpos($txt, '"', $pos) - $pos;
                $srcset = substr($txt, $pos, $len);
                $aSrcset = explode(',', $srcset);
                $aNewSrcset = [];
                foreach ($aSrcset as $src) {
                    $src = trim($src);
                    if (substr($src, 0, 1) == '/') {
                        $aNewSrcset[] = $newBaseUrl . $src;
                    }
                }
                $newSrcset = implode(', ', $aNewSrcset);
                $txt = str_replace($srcset, $newSrcset, $txt);
            }
            return $txt;
        } catch (CustomException $e) {
            return new \WP_Error('absoluteUrl_error', __('Error in absoluteUrl().', 'rrze-answers'));
        }
    }

    /**
     * Fetch and validate the complete remote collection before local writes.
     *
     * Sync deliberately bypasses the HTTP cache. Deletion decisions must be
     * based on one complete, internally consistent snapshot of every page.
     *
     * @return array<int|string, array<string, mixed>>|\WP_Error
     */
    protected function getEntries(
        string $sourceUrl,
        string $selectedCategories,
        string $contentType
    ) {
        try {
            $remoteEntriesById = [];
            $categoryTaxonomy = 'rrze_' . $contentType . '_category';
            $tagTaxonomy = 'rrze_' . $contentType . '_tag';
            $categoryFilter = '&filter[' . $categoryTaxonomy . ']=' . rawurlencode($selectedCategories);
            $currentPage = 1;
            $expectedTotalPages = null;

            do {
                $response = $this->remoteGet(
                    $sourceUrl . '/' . ENDPOINT . $contentType
                        . '?per_page=100&page=' . $currentPage . $categoryFilter,
                    [],
                    true,
                    false
                );

                if (is_wp_error($response)) {
                    return $response;
                }

                $statusCode = (int) wp_remote_retrieve_response_code($response);

                if ($statusCode === 403) {
                    return new \WP_Error('remote_forbidden', __('Import not allowed by source site.', 'rrze-answers'));
                }

                if ($statusCode !== 200) {
                    return new \WP_Error(
                        'remote_http_error',
                        sprintf(
                            /* translators: %d: HTTP response status code. */
                            __('The source site returned HTTP status %d. Existing synchronized entries were preserved.', 'rrze-answers'),
                            $statusCode
                        )
                    );
                }

                $responseBody = wp_remote_retrieve_body($response);
                $pageEntries = json_decode($responseBody, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($pageEntries) || !array_is_list($pageEntries)) {
                    return new \WP_Error(
                        'remote_invalid_response',
                        __('The source site returned an invalid response. Existing synchronized entries were preserved.', 'rrze-answers')
                    );
                }

                $reportedTotalPages = (int) wp_remote_retrieve_header($response, 'x-wp-totalpages');
                if ($reportedTotalPages > 0) {
                    if ($expectedTotalPages !== null && $expectedTotalPages !== $reportedTotalPages) {
                        return new \WP_Error(
                            'remote_inconsistent_pagination',
                            __('The source content changed while it was being fetched. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    $expectedTotalPages = $reportedTotalPages;
                }

                if ($pageEntries === []) {
                    if ($expectedTotalPages !== null && $currentPage <= $expectedTotalPages) {
                        return new \WP_Error(
                            'remote_incomplete_response',
                            __('The source site returned an incomplete response. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    break;
                }

                foreach ($pageEntries as $remoteEntry) {
                    if (
                        !is_array($remoteEntry)
                        || !array_key_exists('source', $remoteEntry)
                        || !isset(
                            $remoteEntry['id'],
                            $remoteEntry['title']['rendered'],
                            $remoteEntry['content']['rendered']
                        )
                    ) {
                        return new \WP_Error(
                            'remote_invalid_entry',
                            __('The source site returned an incomplete entry. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    // Imported content is never re-exported by a downstream site.
                    if ($remoteEntry['source'] !== 'website') {
                        continue;
                    }

                    $normalizedContent = $this->absoluteUrl(
                        $remoteEntry['content']['rendered'],
                        $sourceUrl
                    );
                    if (is_wp_error($normalizedContent)) {
                        return $normalizedContent;
                    }

                    $remoteId = $remoteEntry['remoteID'] ?? $remoteEntry['id'];
                    $remoteChanged = $remoteEntry['remoteChanged'] ?? null;

                    if ($remoteId === '' || $remoteChanged === null) {
                        return new \WP_Error(
                            'remote_invalid_entry',
                            __('The source site returned an incomplete entry. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    if (isset($remoteEntriesById[$remoteId])) {
                        return new \WP_Error(
                            'remote_duplicate_entry',
                            __('The source site returned duplicate entries. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    $categoryNames = $this->restTermsToNames(
                        $remoteEntry[$categoryTaxonomy] ?? [],
                        $categoryTaxonomy
                    );
                    $tagNames = $this->restTermsToNames(
                        $remoteEntry[$tagTaxonomy] ?? [],
                        $tagTaxonomy
                    );

                    $remoteEntriesById[$remoteId] = [
                        'id' => $remoteEntry['id'],
                        'title' => $remoteEntry['title']['rendered'],
                        'content' => $normalizedContent,
                        'lang' => $remoteEntry['lang'] ?? '',
                        $categoryTaxonomy => $categoryNames,
                        'remoteID' => $remoteId,
                        'remoteChanged' => $remoteChanged,
                        $tagTaxonomy => implode(',', $tagNames),
                        'URLhasSlider' => strpos($normalizedContent, 'slider') !== false
                            ? ($remoteEntry['link'] ?? $sourceUrl)
                            : false,
                    ];
                }

                $currentPage++;
            } while ($expectedTotalPages === null || $currentPage <= $expectedTotalPages);

            return $remoteEntriesById;
        } catch (\Throwable $exception) {
            return new \WP_Error('getEntry_error', __('Error in getEntry().', 'rrze-answers'));
        }
    }

    /**
     * Resolve a comma-separated list of remote tag names to local term IDs.
     *
     * @return int[]|\WP_Error
     */
    public function setTags(
        $terms,
        $sourceIdentifier,
        $contentType,
        ?SyncOperationJournal $operationJournal = null
    )
    {
        $termIds = [];
        $taxonomy = 'rrze_' . $contentType . '_tag';

        try {
            if (!$terms) {
                return $termIds;
            }

            $tagNames = explode(',', (string) $terms);

            foreach ($tagNames as $tagName) {
                $tagName = trim($tagName);

                if ($tagName === '') {
                    continue;
                }

                $term = term_exists($tagName, $taxonomy);
                $isNewTerm = !$term;

                if ($isNewTerm) {
                    $term = wp_insert_term($tagName, $taxonomy);
                }

                if (is_wp_error($term)) {
                    return $term;
                }

                $termId = (int) $term['term_id'];

                if ($termId <= 0) {
                    return new \WP_Error(
                        'sync_tag_invalid',
                        __('A synchronized tag could not be resolved. No entries were deleted.', 'rrze-answers')
                    );
                }

                if ($isNewTerm) {
                    update_term_meta($termId, 'source', $sourceIdentifier);
                    $operationJournal?->recordCreatedTerm($termId, $taxonomy);
                }

                $termIds[] = $termId;
            }

            return $termIds;
        } catch (\Throwable $exception) {
            return new \WP_Error('setTags_error', __('Error in setTags().', 'rrze-answers'));
        }
    }

    /**
     * Return locally stored entries indexed by their remote ID.
     *
     * The public method and its historic array keys are retained for backwards
     * compatibility. New sync code uses descriptive variable names around the
     * legacy shape.
     *
     * @return array<int|string, array{postID: int, remoteChanged: mixed}>|\WP_Error
     */
    public function getEntriesRemoteIDs($sourceIdentifier, $contentType)
    {
        try {
            $entriesByRemoteId = [];
            $postIds = get_posts([
                'post_type' => 'rrze_' . $contentType,
                'meta_key' => 'source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_value' => $sourceIdentifier, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'fields' => 'ids',
                'numberposts' => -1,
            ]);

            foreach ($postIds as $postId) {
                $remoteId = get_post_meta($postId, 'remoteID', true);

                if ($remoteId === '') {
                    continue;
                }

                $entriesByRemoteId[$remoteId] = [
                    'postID' => (int) $postId,
                    'remoteChanged' => get_post_meta($postId, 'remoteChanged', true),
                ];
            }

            return $entriesByRemoteId;
        } catch (\Throwable $exception) {
            return new \WP_Error('getEntriesRemoteIDs_error', __('Error in getEntriesRemoteIDs().', 'rrze-answers'));
        }
    }

    /**
     * Synchronize one complete FAQ or glossary collection from one source.
     *
     * Safety contract:
     * 1. Fetch and validate every remote page before writing locally.
     * 2. Abort immediately when a term or post write fails.
     * 3. Trash obsolete entries only after every remote entry was processed.
     * 4. Compensate recorded writes if a later operation fails.
     *
     * Historic result keys are preserved because the admin and cron reporting
     * code consumes them directly.
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
            $postType = 'rrze_' . $contentType;
            $tagTaxonomy = $postType . '_tag';
            $categoryTaxonomy = $postType . '_category';

            $remainingLocalEntries = $this->getEntriesRemoteIDs($sourceIdentifier, $contentType);
            if (is_wp_error($remainingLocalEntries)) {
                return $remainingLocalEntries;
            }

            $remoteEntries = $this->getEntries(
                (string) $sourceUrl,
                (string) $selectedCategories,
                (string) $contentType
            );
            if (is_wp_error($remoteEntries)) {
                return $remoteEntries;
            }

            // A remote empty collection is ambiguous when local imports exist.
            // Whole-source removal remains an explicit settings operation.
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

                // Unchanged entries require no term or post writes. This also
                // avoids creating orphaned remote terms unnecessarily.
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
                    (string) $sourceIdentifier,
                    (string) $contentType,
                    $operationJournal
                );
                if (is_wp_error($categoryIds)) {
                    return $this->rollbackAfterFailure($categoryIds, $operationJournal);
                }

                if ($localEntry !== null) {
                    $updatedPost = $this->updateSynchronizedPost(
                        $localEntry['postID'],
                        $remoteEntry,
                        (string) $sourceIdentifier,
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

                $insertedPost = $this->insertSynchronizedPost(
                    $postType,
                    $remoteEntry,
                    (string) $sourceIdentifier,
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

            $deletedCount = $this->trashObsoleteEntries(
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
     * @param array<string, mixed> $remoteEntry
     * @param int[]                $categoryIds
     * @param int[]                $tagIds
     * @return int|\WP_Error
     */
    private function updateSynchronizedPost(
        int $postId,
        array $remoteEntry,
        string $sourceIdentifier,
        string $categoryTaxonomy,
        string $tagTaxonomy,
        array $categoryIds,
        array $tagIds
    ) {
        $updatedPost = wp_update_post([
            'ID' => $postId,
            'post_name' => sanitize_title($remoteEntry['title']),
            'post_title' => $remoteEntry['title'],
            'post_content' => $remoteEntry['content'],
            'meta_input' => [
                'source' => $sourceIdentifier,
                'lang' => $remoteEntry['lang'],
                'remoteID' => $remoteEntry['remoteID'],
                'remoteChanged' => $remoteEntry['remoteChanged'],
            ],
            'tax_input' => [
                $categoryTaxonomy => $categoryIds,
                $tagTaxonomy => $tagIds,
            ],
        ], true);

        if (is_wp_error($updatedPost)) {
            return $updatedPost;
        }

        return $updatedPost;
    }

    /**
     * @param array<string, mixed> $remoteEntry
     * @param int[]                $categoryIds
     * @param int[]                $tagIds
     * @return int|\WP_Error
     */
    private function insertSynchronizedPost(
        string $postType,
        array $remoteEntry,
        string $sourceIdentifier,
        string $categoryTaxonomy,
        string $tagTaxonomy,
        array $categoryIds,
        array $tagIds
    ) {
        $insertedPost = wp_insert_post([
            'post_type' => $postType,
            'post_name' => sanitize_title($remoteEntry['title']),
            'post_title' => $remoteEntry['title'],
            'post_content' => $remoteEntry['content'],
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_status' => 'publish',
            'meta_input' => [
                'source' => $sourceIdentifier,
                'lang' => $remoteEntry['lang'],
                'remoteID' => $remoteEntry['remoteID'],
                'remoteChanged' => $remoteEntry['remoteChanged'],
                'sortfield' => '',
            ],
            'tax_input' => [
                $categoryTaxonomy => $categoryIds,
                $tagTaxonomy => $tagIds,
            ],
        ], true);

        if (is_wp_error($insertedPost)) {
            return $insertedPost;
        }

        return $insertedPost;
    }

    /**
     * Move entries left over after reconciliation to the WordPress Trash.
     *
     * @param array<int|string, array{postID: int, remoteChanged: mixed}> $obsoleteEntries
     * @return int|\WP_Error
     */
    private function trashObsoleteEntries(
        array $obsoleteEntries,
        SyncOperationJournal $operationJournal
    )
    {
        if ($obsoleteEntries !== [] && (!defined('EMPTY_TRASH_DAYS') || !EMPTY_TRASH_DAYS)) {
            return new \WP_Error(
                'sync_trash_disabled',
                __('Obsolete synchronized entries were preserved because WordPress Trash is disabled.', 'rrze-answers')
            );
        }

        $trashedCount = 0;

        foreach ($obsoleteEntries as $obsoleteEntry) {
            $post = get_post($obsoleteEntry['postID']);
            if (!$post instanceof \WP_Post) {
                return new \WP_Error(
                    'sync_trash_failed',
                    __('An obsolete synchronized entry could not be loaded for cleanup.', 'rrze-answers')
                );
            }

            $previousStatus = $post->post_status;
            $trashedPost = wp_trash_post($post->ID);

            if (!$trashedPost) {
                return new \WP_Error(
                    'sync_trash_failed',
                    __('An obsolete synchronized entry could not be moved to Trash.', 'rrze-answers')
                );
            }

            $operationJournal->recordTrashedPost($post->ID, $previousStatus);
            $trashedCount++;
        }

        return $trashedCount;
    }

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
     * Normalize REST taxonomy values (term IDs or legacy names) to term names.
     *
     * @param mixed  $terms
     * @param string $taxonomy
     * @return string[]
     */
    private function restTermsToNames($terms, string $taxonomy): array
    {
        if (!is_array($terms)) {
            return [];
        }

        $names = [];

        foreach ($terms as $term) {
            if (is_numeric($term)) {
                $term_object = get_term((int) $term, $taxonomy);
                if ($term_object && !is_wp_error($term_object)) {
                    $names[] = $term_object->name;
                }
                continue;
            }

            if (is_string($term) && $term !== '') {
                $names[] = $term;
            }
        }

        return $names;
    }

    /**
     * Get remote content.
     *
     * @param string $url
     * @param array  $args
     * @param bool   $safe
     * @param bool   $use_cache
     * @return array|\WP_Error
     */
    private function remoteGet(string $url, array $args = [], bool $safe = true, bool $use_cache = true)
    {
        $cache_key = 'rrze_remote_' . md5($url);
        $cached = $use_cache ? get_transient($cache_key) : false;

        if (false !== $cached) {
            return $cached;
        }

        try {
            if (empty($args)) {
                $args = [
                    'sslverify' => defined('WP_DEBUG') && WP_DEBUG ? false : true,
                    'timeout' => 5,
                ];
            }

            if ($safe) {
                $ret = wp_safe_remote_get($url, $args);
            } else {
                $ret = wp_remote_get($url, $args);
            }

            // Only successful responses are safe to cache. Content sync bypasses
            // this cache so deletion decisions are never based on stale pages.
            if ($use_cache && !is_wp_error($ret) && (int) wp_remote_retrieve_response_code($ret) === 200) {
                set_transient($cache_key, $ret, 10 * MINUTE_IN_SECONDS);
            }

            return $ret;
        } catch (\Throwable $e) {
            return new \WP_Error('remoteGet_error', __('Error in remoteGet().', 'rrze-answers'));
        }
    }

    public function getDomains()
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

    public function checkDomain($identifier, $url, $domains)
    {
        // returns array('status' => TRUE, 'msg' => '')
        // on error returns array('status' => FALSE, 'ret' => error-message)
        $aRet = array('status' => FALSE, 'msg' => '');

        if (in_array($url, $domains)) {
            $aRet['msg'] = $url . ' ' . __('is already in use.', 'rrze-answers');
            return $aRet;
        } elseif (array_key_exists($identifier, $domains)) {
            $aRet['msg'] = $identifier . ' ' . __('is already in use.', 'rrze-answers');
            return $aRet;
        }

        $aSubEndpoints = ['faq', 'synonym', 'glossary'];

        foreach ($aSubEndpoints as $sub) {
            $request = wp_remote_get($url . '/' . ENDPOINT . $sub . '?per_page=1');
            $status_code = wp_remote_retrieve_response_code($request);

            if ($status_code != '200') {
                $aRet['msg'] = $url . ' ' . __('is not valid.', 'rrze-answers');
            } else {
                $content = json_decode(wp_remote_retrieve_body($request), TRUE);

                if (!$content) {
                    $aRet['ret'] = $url . ' ' . __(' does not support this plugin.', 'rrze-answers');
                } else {
                    $aRet['status'] = TRUE;
                    break;
                }
            }
        }

        return $aRet;
    }
}
