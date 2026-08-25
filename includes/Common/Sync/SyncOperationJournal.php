<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Sync;

use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Records local sync mutations and compensates them when a later step fails.
 *
 * WordPress does not expose a transaction spanning posts, metadata, terms,
 * hooks and caches. This journal therefore implements a best-effort rollback
 * exclusively through WordPress APIs. It restores the state controlled by the
 * sync process; external side effects triggered by third-party hooks remain
 * outside its control.
 */
final class SyncOperationJournal
{
    private const SYNCHRONIZED_META_KEYS = [
        'source',
        'lang',
        'remoteID',
        'remoteChanged',
    ];

    /** @var int[] */
    private array $insertedPostIds = [];

    /**
     * @var array<int, array{
     *     post: array{ID: int, post_name: string, post_title: string, post_content: string},
     *     meta: array<string, array<int, mixed>>,
     *     terms: array<string, int[]>
     * }>
     */
    private array $updatedPostSnapshots = [];

    /** @var array<string, array{termId: int, taxonomy: string}> */
    private array $createdTerms = [];

    /** @var array<int, string> Post ID mapped to its status before trashing. */
    private array $trashedPosts = [];

    /**
     * Capture exactly the post state modified by SyncAPI before an update.
     *
     * @param string[] $taxonomies
     */
    public function capturePostBeforeUpdate(int $postId, array $taxonomies): ?WP_Error
    {
        if (isset($this->updatedPostSnapshots[$postId])) {
            return null;
        }

        $post = get_post($postId);
        if (!$post instanceof WP_Post) {
            return new WP_Error(
                'sync_snapshot_failed',
                __('A synchronized entry could not be prepared for rollback.', 'rrze-answers')
            );
        }

        $termSnapshots = [];
        foreach ($taxonomies as $taxonomy) {
            $termIds = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($termIds)) {
                return $termIds;
            }

            $termSnapshots[$taxonomy] = array_map('intval', $termIds);
        }

        $metaSnapshots = [];
        foreach (self::SYNCHRONIZED_META_KEYS as $metaKey) {
            $metaSnapshots[$metaKey] = get_post_meta($postId, $metaKey, false);
        }

        $this->updatedPostSnapshots[$postId] = [
            'post' => [
                'ID' => $postId,
                'post_name' => $post->post_name,
                'post_title' => $post->post_title,
                'post_content' => $post->post_content,
            ],
            'meta' => $metaSnapshots,
            'terms' => $termSnapshots,
        ];

        return null;
    }

    public function recordInsertedPost(int $postId): void
    {
        $this->insertedPostIds[] = $postId;
    }

    public function recordCreatedTerm(int $termId, string $taxonomy): void
    {
        $this->createdTerms[$taxonomy . ':' . $termId] = [
            'termId' => $termId,
            'taxonomy' => $taxonomy,
        ];
    }

    public function recordTrashedPost(int $postId, string $previousStatus): void
    {
        $this->trashedPosts[$postId] = $previousStatus;
    }

    /**
     * Compensate recorded operations in reverse dependency order.
     *
     * @return WP_Error|null Null when every compensation succeeded.
     */
    public function rollback(): ?WP_Error
    {
        $rollbackError = new WP_Error();

        $this->restoreTrashedPosts($rollbackError);
        $this->restoreUpdatedPosts($rollbackError);
        $this->removeInsertedPosts($rollbackError);
        $this->removeCreatedOrphanTerms($rollbackError);

        return $rollbackError->has_errors() ? $rollbackError : null;
    }

    private function restoreTrashedPosts(WP_Error $rollbackError): void
    {
        foreach (array_reverse($this->trashedPosts, true) as $postId => $previousStatus) {
            $restoredPost = wp_untrash_post($postId);

            if (!$restoredPost) {
                $rollbackError->add(
                    'sync_untrash_failed',
                    sprintf(
                        /* translators: %d: post ID. */
                        __('Synchronized entry %d could not be restored from Trash.', 'rrze-answers'),
                        $postId
                    )
                );
                continue;
            }

            if (get_post_status($postId) === $previousStatus) {
                continue;
            }

            $statusRestore = wp_update_post([
                'ID' => $postId,
                'post_status' => $previousStatus,
            ], true);

            if (is_wp_error($statusRestore)) {
                $rollbackError->add(
                    'sync_status_restore_failed',
                    $statusRestore->get_error_message()
                );
            }
        }
    }

    private function restoreUpdatedPosts(WP_Error $rollbackError): void
    {
        foreach (array_reverse($this->updatedPostSnapshots, true) as $postId => $snapshot) {
            $postRestore = wp_update_post($snapshot['post'], true);

            if (is_wp_error($postRestore)) {
                $rollbackError->add(
                    'sync_post_restore_failed',
                    $postRestore->get_error_message()
                );
            }

            foreach ($snapshot['meta'] as $metaKey => $values) {
                delete_post_meta($postId, $metaKey);

                foreach ($values as $value) {
                    if (add_post_meta($postId, $metaKey, $value) === false) {
                        $rollbackError->add(
                            'sync_meta_restore_failed',
                            sprintf(
                                /* translators: 1: metadata key, 2: post ID. */
                                __('Metadata %1$s for synchronized entry %2$d could not be restored.', 'rrze-answers'),
                                $metaKey,
                                $postId
                            )
                        );
                    }
                }
            }

            foreach ($snapshot['terms'] as $taxonomy => $termIds) {
                $termRestore = wp_set_object_terms($postId, $termIds, $taxonomy, false);

                if (is_wp_error($termRestore)) {
                    $rollbackError->add(
                        'sync_terms_restore_failed',
                        $termRestore->get_error_message()
                    );
                }
            }
        }
    }

    private function removeInsertedPosts(WP_Error $rollbackError): void
    {
        foreach (array_reverse($this->insertedPostIds) as $postId) {
            if (!wp_delete_post($postId, true)) {
                $rollbackError->add(
                    'sync_insert_rollback_failed',
                    sprintf(
                        /* translators: %d: post ID. */
                        __('New synchronized entry %d could not be removed during rollback.', 'rrze-answers'),
                        $postId
                    )
                );
            }
        }
    }

    private function removeCreatedOrphanTerms(WP_Error $rollbackError): void
    {
        foreach (array_reverse($this->createdTerms) as $createdTerm) {
            $objectIds = get_objects_in_term(
                $createdTerm['termId'],
                $createdTerm['taxonomy']
            );

            if (is_wp_error($objectIds)) {
                $rollbackError->add(
                    'sync_term_usage_check_failed',
                    $objectIds->get_error_message()
                );
                continue;
            }

            // A concurrently attached term is no longer owned solely by this
            // operation and must not be removed during compensation.
            if ($objectIds !== []) {
                continue;
            }

            $deletedTerm = wp_delete_term(
                $createdTerm['termId'],
                $createdTerm['taxonomy']
            );

            if (is_wp_error($deletedTerm) || !$deletedTerm) {
                $rollbackError->add(
                    'sync_term_rollback_failed',
                    is_wp_error($deletedTerm)
                        ? $deletedTerm->get_error_message()
                        : __('A newly created synchronized term could not be removed during rollback.', 'rrze-answers')
                );
            }
        }
    }
}
