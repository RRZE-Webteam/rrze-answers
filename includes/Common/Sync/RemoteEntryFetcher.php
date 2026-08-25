<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Sync;

use RRZE\Answers\Common\API\REST\EntryTaxonomyFields;

defined('ABSPATH') || exit;

/**
 * Fetches and validates complete entry snapshots from a remote Answers site.
 *
 * The fetcher deliberately does not cache responses. A synchronization may
 * only remove obsolete local entries after every page of one internally
 * consistent remote snapshot has been received and validated.
 */
final class RemoteEntryFetcher
{
    private RemoteTaxonomyResolver $taxonomyResolver;

    /**
     * Create a fetcher with a backwards-compatible taxonomy resolver.
     */
    public function __construct(?RemoteTaxonomyResolver $taxonomyResolver = null)
    {
        $this->taxonomyResolver = $taxonomyResolver ?? new RemoteTaxonomyResolver();
    }

    /**
     * Fetch a complete remote collection indexed by its stable remote IDs.
     *
     * @param string $sourceUrl         Base URL of the source WordPress site.
     * @param string $selectedCategories Category filter configured locally.
     * @param string $contentType       Answers type without the `rrze_` prefix.
     * @return array<int|string, array<string, mixed>>|\WP_Error
     */
    public function fetch(
        string $sourceUrl,
        string $selectedCategories,
        string $contentType
    ) {
        try {
            $remoteEntriesById = [];
            $this->taxonomyResolver->reset();
            $categoryTaxonomy = 'rrze_' . $contentType . '_category';
            $tagTaxonomy = 'rrze_' . $contentType . '_tag';
            $categoryFilter = '&filter[' . $categoryTaxonomy . ']=' . rawurlencode($selectedCategories);
            $currentPage = 1;
            $expectedTotalPages = null;

            do {
                $response = $this->request(
                    $sourceUrl . '/' . ENDPOINT . $contentType
                        . '?per_page=100&page=' . $currentPage
                        . '&_embed=wp:term' . $categoryFilter
                );

                if (is_wp_error($response)) {
                    return $response;
                }

                $statusCode = (int) wp_remote_retrieve_response_code($response);

                if ($statusCode === 403) {
                    return new \WP_Error(
                        'remote_forbidden',
                        __('Import not allowed by source site.', 'rrze-answers')
                    );
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

                $pageEntries = json_decode(wp_remote_retrieve_body($response), true);

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
                    $normalizedEntry = $this->normalizeEntry(
                        $remoteEntry,
                        $sourceUrl,
                        $categoryTaxonomy,
                        $tagTaxonomy
                    );

                    if (is_wp_error($normalizedEntry)) {
                        return $normalizedEntry;
                    }

                    if ($normalizedEntry === null) {
                        continue;
                    }

                    $remoteId = $normalizedEntry['remoteID'];
                    if (isset($remoteEntriesById[$remoteId])) {
                        return new \WP_Error(
                            'remote_duplicate_entry',
                            __('The source site returned duplicate entries. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    $remoteEntriesById[$remoteId] = $normalizedEntry;
                }

                $currentPage++;
            } while ($expectedTotalPages === null || $currentPage <= $expectedTotalPages);

            return $remoteEntriesById;
        } catch (\Throwable $exception) {
            return new \WP_Error('getEntry_error', __('Error in getEntry().', 'rrze-answers'));
        }
    }

    /**
     * Convert relative content URLs to absolute URLs rooted at the source.
     *
     * This method is public so the historic protected SyncAPI wrapper can
     * retain its behavior for subclasses while delegating the implementation.
     *
     * @return string|\WP_Error
     */
    public function normalizeContentUrls(string $content, string $sourceUrl)
    {
        try {
            $normalizedContent = preg_replace_callback(
                '/\b(href|src|background)="([^"]*)"/i',
                function (array $matches) use ($sourceUrl): string {
                    return $matches[1] . '="' . $this->makeAbsoluteUrl($matches[2], $sourceUrl) . '"';
                },
                $content
            );

            if ($normalizedContent === null) {
                return new \WP_Error(
                    'absoluteUrl_error',
                    __('Error in absoluteUrl().', 'rrze-answers')
                );
            }

            $normalizedContent = preg_replace_callback(
                '/\bsrcset="([^"]*)"/i',
                function (array $matches) use ($sourceUrl): string {
                    $candidates = array_map('trim', explode(',', $matches[1]));
                    $normalizedCandidates = [];

                    foreach ($candidates as $candidate) {
                        if ($candidate === '') {
                            continue;
                        }

                        $candidateParts = preg_split('/\s+/', $candidate, 2);
                        $url = $candidateParts[0];
                        $descriptor = $candidateParts[1] ?? '';
                        $normalizedCandidates[] = trim(
                            $this->makeAbsoluteUrl($url, $sourceUrl) . ' ' . $descriptor
                        );
                    }

                    return 'srcset="' . implode(', ', $normalizedCandidates) . '"';
                },
                $normalizedContent
            );

            if ($normalizedContent === null) {
                return new \WP_Error(
                    'absoluteUrl_error',
                    __('Error in absoluteUrl().', 'rrze-answers')
                );
            }

            return $normalizedContent;
        } catch (\Throwable $exception) {
            return new \WP_Error('absoluteUrl_error', __('Error in absoluteUrl().', 'rrze-answers'));
        }
    }

    /**
     * Validate and normalize one REST entry.
     *
     * Imported entries are ignored because they must never be re-exported by
     * another site in a synchronization chain.
     *
     * @param mixed $remoteEntry
     * @return array<string, mixed>|null|\WP_Error
     */
    private function normalizeEntry(
        $remoteEntry,
        string $sourceUrl,
        string $categoryTaxonomy,
        string $tagTaxonomy
    ) {
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

        if ($remoteEntry['source'] !== 'website') {
            return null;
        }

        $normalizedContent = $this->normalizeContentUrls(
            (string) $remoteEntry['content']['rendered'],
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

        $categoryNames = $this->taxonomyResolver->resolveNames(
            $remoteEntry,
            $sourceUrl,
            $categoryTaxonomy,
            EntryTaxonomyFields::CATEGORY_NAMES_FIELD
        );
        if (is_wp_error($categoryNames)) {
            return $categoryNames;
        }

        $tagNames = $this->taxonomyResolver->resolveNames(
            $remoteEntry,
            $sourceUrl,
            $tagTaxonomy,
            EntryTaxonomyFields::TAG_NAMES_FIELD
        );
        if (is_wp_error($tagNames)) {
            return $tagNames;
        }

        return [
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

    /**
     * Convert a single relative URL to an absolute source URL.
     */
    private function makeAbsoluteUrl(string $url, string $sourceUrl): string
    {
        if (
            $url === ''
            || str_starts_with($url, '#')
            || str_starts_with($url, '//')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1
        ) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            $scheme = (string) wp_parse_url($sourceUrl, PHP_URL_SCHEME);
            $host = (string) wp_parse_url($sourceUrl, PHP_URL_HOST);
            $port = wp_parse_url($sourceUrl, PHP_URL_PORT);

            if ($scheme !== '' && $host !== '') {
                return $scheme . '://' . $host . ($port ? ':' . $port : '') . $url;
            }
        }

        return trailingslashit($sourceUrl) . ltrim($url, '/');
    }

    /**
     * Request one uncached remote page using WordPress SSRF protection.
     *
     * @return array<string, mixed>|\WP_Error
     */
    private function request(string $url)
    {
        try {
            return wp_safe_remote_get(
                $url,
                [
                    'sslverify' => !(defined('WP_DEBUG') && WP_DEBUG),
                    'timeout' => 5,
                ]
            );
        } catch (\Throwable $exception) {
            return new \WP_Error('remoteGet_error', __('Error in remoteGet().', 'rrze-answers'));
        }
    }
}
