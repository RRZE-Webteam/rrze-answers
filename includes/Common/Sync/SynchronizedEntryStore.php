<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Sync;

defined('ABSPATH') || exit;

/**
 * Persists synchronized entries and their taxonomy terms through WordPress.
 *
 * This class owns local WordPress state only. It does not perform remote HTTP
 * requests or decide the order in which a synchronization is reconciled.
 */
final class SynchronizedEntryStore
{
    /**
     * Permanently delete entries belonging to an explicitly removed source.
     *
     * Automatic reconciliation uses Trash instead. Permanent deletion remains
     * available only for the existing settings workflow that removes a source.
     */
    public function deleteEntries(string $sourceIdentifier, string $contentType): int
    {
        $deletedCount = 0;
        $entries = get_posts([
            'post_type' => 'rrze_' . $contentType,
            'meta_key' => 'source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => $sourceIdentifier, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'numberposts' => -1,
        ]);

        foreach ($entries as $entry) {
            wp_delete_post($entry->ID, true);
            $deletedCount++;
        }

        return $deletedCount;
    }

    /**
     * Delete taxonomy terms created for an explicitly removed source.
     *
     * @return void|\WP_Error
     */
    public function deleteTaxonomies(string $sourceIdentifier, string $taxonomy)
    {
        try {
            $termIds = get_terms([
                'hide_empty' => false,
                'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key' => 'source',
                        'value' => $sourceIdentifier,
                        'compare' => '=',
                    ],
                ],
                'taxonomy' => $taxonomy,
                'fields' => 'ids',
            ]);

            if (is_wp_error($termIds)) {
                return $termIds;
            }

            foreach ($termIds as $termId) {
                $result = wp_delete_term((int) $termId, $taxonomy);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
        } catch (\Throwable $exception) {
            return new \WP_Error(
                'deleteTaxonomies_error',
                __('Error in deleteTaxonomies().', 'rrze-answers')
            );
        }
    }

    /**
     * Resolve remote category names to local term IDs.
     *
     * Existing local terms retain their ownership metadata. Only terms created
     * by this synchronization are marked with the remote source identifier.
     *
     * @param string[] $categoryNames
     * @return int[]|\WP_Error
     */
    public function resolveCategoryIds(
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

    /**
     * Resolve a comma-separated list of remote tag names to local term IDs.
     *
     * @param mixed $terms
     * @return int[]|\WP_Error
     */
    public function resolveTagIds(
        $terms,
        string $sourceIdentifier,
        string $contentType,
        ?SyncOperationJournal $operationJournal = null
    ) {
        $termIds = [];
        $taxonomy = 'rrze_' . $contentType . '_tag';

        try {
            if (!$terms) {
                return $termIds;
            }

            foreach (explode(',', (string) $terms) as $tagName) {
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
     * Return locally stored entries indexed by their stable remote ID.
     *
     * @return array<int|string, array{postID: int, remoteChanged: mixed}>|\WP_Error
     */
    public function getEntriesByRemoteId(string $sourceIdentifier, string $contentType)
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
            return new \WP_Error(
                'getEntriesRemoteIDs_error',
                __('Error in getEntriesRemoteIDs().', 'rrze-answers')
            );
        }
    }

    /**
     * Update an existing synchronized post and its terms.
     *
     * @param array<string, mixed> $remoteEntry
     * @param int[]                $categoryIds
     * @param int[]                $tagIds
     * @return int|\WP_Error
     */
    public function updatePost(
        int $postId,
        array $remoteEntry,
        string $sourceIdentifier,
        string $categoryTaxonomy,
        string $tagTaxonomy,
        array $categoryIds,
        array $tagIds
    ) {
        return wp_update_post([
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
    }

    /**
     * Insert a new synchronized post and its terms.
     *
     * @param array<string, mixed> $remoteEntry
     * @param int[]                $categoryIds
     * @param int[]                $tagIds
     * @return int|\WP_Error
     */
    public function insertPost(
        string $postType,
        array $remoteEntry,
        string $sourceIdentifier,
        string $categoryTaxonomy,
        string $tagTaxonomy,
        array $categoryIds,
        array $tagIds
    ) {
        return wp_insert_post([
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
    }

    /**
     * Move entries left over after reconciliation to the WordPress Trash.
     *
     * @param array<int|string, array{postID: int, remoteChanged: mixed}> $obsoleteEntries
     * @return int|\WP_Error
     */
    public function trashObsoleteEntries(
        array $obsoleteEntries,
        SyncOperationJournal $operationJournal
    ) {
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
}
