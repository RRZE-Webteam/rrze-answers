<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Sync;

use WP_Error;
use WP_Post;

defined('ABSPATH') || exit;

/**
 * Removes configured synchronization sources through a recoverable workflow.
 *
 * Posts are moved to WordPress Trash on the current site and recorded in an
 * operation journal. If any later post cannot be trashed, all posts already
 * moved by the same request are restored to their previous statuses.
 *
 * Taxonomy terms are intentionally retained while posts remain recoverable.
 * WordPress preserves term relationships in Trash, and terms may also be shared
 * with local content or another source. Permanent orphan cleanup therefore
 * belongs to a later, explicit maintenance operation after Trash expiration.
 */
final class SynchronizedSourceRemovalService
{
    private const SYNCHRONIZED_CONTENT_TYPES = ['faq', 'glossary'];

    /**
     * Remove one or more registered sources atomically from local content.
     *
     * Every identifier is validated before any post is changed. The caller may
     * remove the corresponding settings only after receiving a successful
     * result.
     *
     * @param string[]             $sourceIdentifiers Selected source identifiers.
     * @param array<string, string> $registeredDomains Current configured sources.
     * @return array{trashedCount: int, removedSourceIdentifiers: string[]}|WP_Error
     */
    public function remove(array $sourceIdentifiers, array $registeredDomains)
    {
        $validatedIdentifiers = $this->validateIdentifiers(
            $sourceIdentifiers,
            $registeredDomains
        );
        if (is_wp_error($validatedIdentifiers)) {
            return $validatedIdentifiers;
        }

        $operationJournal = new SyncOperationJournal();

        try {
            $postIds = $this->findSourcePostIds($validatedIdentifiers);

            if (
                $postIds !== []
                && (!defined('EMPTY_TRASH_DAYS') || !EMPTY_TRASH_DAYS)
            ) {
                return new WP_Error(
                    'source_removal_trash_disabled',
                    __('The synchronization source was preserved because WordPress Trash is disabled.', 'rrze-answers')
                );
            }

            $trashedCount = 0;

            foreach ($postIds as $postId) {
                $post = get_post($postId);

                if (!$post instanceof WP_Post) {
                    return $this->rollbackAfterFailure(
                        new WP_Error(
                            'source_removal_post_missing',
                            __('A synchronized entry could not be loaded. No sources were removed.', 'rrze-answers')
                        ),
                        $operationJournal
                    );
                }

                $currentSource = (string) get_post_meta($postId, 'source', true);
                if (!in_array($currentSource, $validatedIdentifiers, true)) {
                    return $this->rollbackAfterFailure(
                        new WP_Error(
                            'source_removal_post_changed',
                            __('A synchronized entry changed during source removal. No sources were removed.', 'rrze-answers')
                        ),
                        $operationJournal
                    );
                }

                $previousStatus = $post->post_status;
                $trashedPost = wp_trash_post($postId);

                if (!$trashedPost) {
                    return $this->rollbackAfterFailure(
                        new WP_Error(
                            'source_removal_trash_failed',
                            __('A synchronized entry could not be moved to Trash. No sources were removed.', 'rrze-answers')
                        ),
                        $operationJournal
                    );
                }

                $operationJournal->recordTrashedPost($postId, $previousStatus);
                $trashedCount++;
            }

            return [
                'trashedCount' => $trashedCount,
                'removedSourceIdentifiers' => $validatedIdentifiers,
            ];
        } catch (\Throwable $exception) {
            return $this->rollbackAfterFailure(
                new WP_Error(
                    'source_removal_error',
                    __('The synchronization source could not be removed safely.', 'rrze-answers')
                ),
                $operationJournal
            );
        }
    }

    /**
     * Validate and deduplicate every requested source before local mutations.
     *
     * @param array<mixed>          $sourceIdentifiers
     * @param array<string, string> $registeredDomains
     * @return string[]|WP_Error
     */
    private function validateIdentifiers(
        array $sourceIdentifiers,
        array $registeredDomains
    ) {
        $validatedIdentifiers = [];

        foreach ($sourceIdentifiers as $sourceIdentifier) {
            if (!is_string($sourceIdentifier) || $sourceIdentifier === '' || $sourceIdentifier === 'website') {
                return new WP_Error(
                    'source_removal_invalid_identifier',
                    __('An invalid synchronization source was selected. No sources were removed.', 'rrze-answers')
                );
            }

            if (!array_key_exists($sourceIdentifier, $registeredDomains)) {
                return new WP_Error(
                    'source_removal_not_registered',
                    __('The selected synchronization source is no longer registered. No sources were removed.', 'rrze-answers')
                );
            }

            $validatedIdentifiers[] = $sourceIdentifier;
        }

        return array_values(array_unique($validatedIdentifiers));
    }

    /**
     * Find synchronized FAQ and glossary posts on the current WordPress site.
     *
     * @param string[] $sourceIdentifiers
     * @return int[]
     */
    private function findSourcePostIds(array $sourceIdentifiers): array
    {
        $postIds = [];

        foreach (self::SYNCHRONIZED_CONTENT_TYPES as $contentType) {
            foreach ($sourceIdentifiers as $sourceIdentifier) {
                $sourcePostIds = get_posts([
                    'post_type' => 'rrze_' . $contentType,
                    'post_status' => 'any',
                    'meta_key' => 'source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value' => $sourceIdentifier, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    'fields' => 'ids',
                    'numberposts' => -1,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                ]);

                foreach ($sourcePostIds as $postId) {
                    $postIds[] = (int) $postId;
                }
            }
        }

        return array_values(array_unique($postIds));
    }

    /**
     * Restore already trashed posts and retain the original removal error.
     */
    private function rollbackAfterFailure(
        WP_Error $removalError,
        SyncOperationJournal $operationJournal
    ): WP_Error {
        $rollbackError = $operationJournal->rollback();

        if ($rollbackError !== null) {
            $removalError->add(
                'source_removal_rollback_failed',
                __('Source removal failed and some entries could not be restored.', 'rrze-answers'),
                ['rollbackErrors' => $rollbackError->get_error_messages()]
            );
        }

        return $removalError;
    }
}
