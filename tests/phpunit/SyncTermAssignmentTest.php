<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\API\SyncAPI;
use RRZE\Answers\Common\Sync\SynchronizedEntryStore;
use WP_Error;
use WP_UnitTestCase;

/**
 * Verify compensation for real relationship-write failures in anonymous syncs.
 */
final class SyncTermAssignmentTest extends WP_UnitTestCase
{
    private const SOURCE = 'assignment-source';
    private const SOURCE_URL = 'https://assignment-source.example.org';

    /** @var array<int, array{string, callable}> Filters removed after each test. */
    private array $filters = [];

    private bool $previousErrorSuppression;

    /**
     * Run the synchronization boundary with the same user identity as WP-Cron.
     */
    public function set_up(): void
    {
        parent::set_up();
        wp_set_current_user(0);
        $this->previousErrorSuppression = $GLOBALS['wpdb']->suppress_errors(true);
    }

    /**
     * Remove fault injection before WordPress rolls back the test transaction.
     */
    public function tear_down(): void
    {
        foreach ($this->filters as [$hook, $callback]) {
            remove_filter($hook, $callback, 10);
        }
        $GLOBALS['wpdb']->suppress_errors($this->previousErrorSuppression);
        parent::tear_down();
    }

    /**
     * Undo earlier writes, a partially written target and newly created terms.
     *
     * @dataProvider assignmentFailures
     * @param string $type Synchronized content type.
     * @param bool $update Whether the failing entry already exists locally.
     * @param string $failedKind Category or tag relationship to reject.
     */
    public function testPartialAssignmentFailureRollsBackWholeCollection(
        string $type,
        bool $update,
        string $failedKind
    ): void {
        $earlierPost = $this->createImportedPost($type, 810);
        $obsoletePost = $this->createImportedPost($type, 899);
        $targetPost = $update ? $this->createImportedPost($type, 812) : null;
        $before = $this->snapshotCollection($type);

        $this->mockSnapshot($type, [
            $this->remoteEntry($type, 810),
            $this->remoteEntry($type, 811),
            $this->remoteEntry($type, 812),
        ]);
        $failure = $this->failRelationshipWrites($type, $failedKind, 812, 'INSERT');

        $result = $this->synchronize($type);

        self::assertSame(1, $failure->injected, 'The intended relationship SQL write must actually fail.');
        self::assertCount(1, $failure->errors);
        self::assertNotSame('', $failure->errors[0]);
        self::assertWPError($result);
        self::assertSame('sync_terms_assignment_failed', $result->get_error_code());
        self::assertNotContains('sync_rollback_failed', $result->get_error_codes());
        self::assertSame($before, $this->snapshotCollection($type));
        self::assertSame('publish', get_post_status($earlierPost));
        self::assertSame('publish', get_post_status($obsoletePost));
        if ($targetPost !== null) {
            self::assertSame('publish', get_post_status($targetPost));
        }
        foreach ([810, 811, 812] as $remoteId) {
            self::assertNull(term_exists('New category ' . $remoteId, 'rrze_' . $type . '_category'));
            self::assertNull(term_exists('New tag ' . $remoteId, 'rrze_' . $type . '_tag'));
        }
        self::assertNotNull(term_exists('Original category', 'rrze_' . $type . '_category'));
        self::assertNotNull(term_exists('Original tag', 'rrze_' . $type . '_tag'));
    }

    /**
     * Treat a failed removal as an incomplete assignment and restore both sets.
     *
     * @dataProvider contentTypes
     * @param string $type Synchronized content type.
     */
    public function testFailedEmptyAssignmentRestoresPreviousRelationships(string $type): void
    {
        $this->createImportedPost($type, 812);
        $before = $this->snapshotCollection($type);
        $entry = $this->remoteEntry($type, 812);
        $entry['rrze_' . $type . '_category'] = [];
        $entry['rrze_' . $type . '_tag'] = [];
        $this->mockSnapshot($type, [$entry]);
        $failure = $this->failRelationshipWrites($type, 'tag', 812, 'DELETE');

        $result = $this->synchronize($type);

        self::assertSame(1, $failure->injected);
        self::assertCount(1, $failure->errors);
        self::assertNotSame('', $failure->errors[0]);
        self::assertWPError($result);
        self::assertSame('sync_terms_assignment_failed', $result->get_error_code());
        self::assertNotContains('sync_rollback_failed', $result->get_error_codes());
        self::assertSame($before, $this->snapshotCollection($type));
    }

    /**
     * Surface a second SQL failure during compensation instead of claiming undo.
     */
    public function testFailedRelationshipRestoreIsReportedByTheJournal(): void
    {
        $postId = $this->createImportedPost('faq', 812);
        $this->mockSnapshot('faq', [$this->remoteEntry('faq', 812)]);
        $forwardFailure = $this->failRelationshipWrites('faq', 'tag', 812, 'INSERT');
        $restoreFailure = $this->failRelationshipWrites('faq', 'category', 812, 'INSERT', 1);

        $result = $this->synchronize('faq');

        self::assertSame(1, $forwardFailure->injected);
        self::assertSame(1, $restoreFailure->injected);
        self::assertWPError($result);
        self::assertSame('sync_terms_assignment_failed', $result->get_error_code());
        self::assertContains('sync_rollback_failed', $result->get_error_codes());
        self::assertNotEmpty($result->get_error_data('sync_rollback_failed')['rollbackErrors']);
        self::assertSame('Original entry 812', get_the_title($postId));
        self::assertSame('100', (string) get_post_meta($postId, 'remoteChanged', true));
        self::assertSame(['Original tag'], wp_get_post_terms($postId, 'rrze_faq_tag', ['fields' => 'names']));
        self::assertSame([], wp_get_post_terms($postId, 'rrze_faq_category', ['fields' => 'ids']));
    }

    /**
     * Preserve WordPress's explicit error when the target taxonomy is invalid.
     */
    public function testExplicitWordPressAssignmentErrorIsPreserved(): void
    {
        $postId = $this->createImportedPost('faq', 812);
        $result = (new SynchronizedEntryStore())->replaceTerms($postId, 'missing_sync_taxonomy', []);

        self::assertWPError($result);
        self::assertSame('invalid_taxonomy', $result->get_error_code());
    }

    /**
     * Cover failures before and after the first taxonomy on both write paths.
     *
     * @return array<string, array{string, bool, string}>
     */
    public static function assignmentFailures(): array
    {
        $cases = [];
        foreach (['faq', 'glossary'] as $type) {
            foreach ([false, true] as $update) {
                foreach (['category', 'tag'] as $kind) {
                    $cases[$type . ($update ? ' update ' : ' insert ') . $kind] = [$type, $update, $kind];
                }
            }
        }

        return $cases;
    }

    /**
     * Supply both content types for clearing relationships.
     *
     * @return array<string, array{string}>
     */
    public static function contentTypes(): array
    {
        return ['FAQ' => ['faq'], 'Glossary' => ['glossary']];
    }

    /**
     * Seed an initialized import with relationships already stored locally.
     */
    private function createImportedPost(string $type, int $remoteId): int
    {
        $postId = self::factory()->post->create([
            'post_type' => 'rrze_' . $type,
            'post_status' => 'publish',
            'post_title' => 'Original entry ' . $remoteId,
            'post_content' => '<p>Original content</p>',
            'meta_input' => [
                'source' => self::SOURCE,
                'remoteID' => $remoteId,
                'remoteChanged' => 100,
                'lang' => 'de',
            ],
        ]);
        self::assertNotWPError(wp_set_object_terms($postId, ['Original category'], 'rrze_' . $type . '_category'));
        self::assertNotWPError(wp_set_object_terms($postId, ['Original tag'], 'rrze_' . $type . '_tag'));

        return $postId;
    }

    /**
     * Capture the synchronized data and relationships for exact rollback checks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function snapshotCollection(string $type): array
    {
        $snapshot = [];
        $posts = get_posts([
            'post_type' => 'rrze_' . $type,
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_key' => 'source',
            'meta_value' => self::SOURCE,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        foreach ($posts as $post) {
            $snapshot[$post->ID] = [
                'status' => $post->post_status,
                'title' => $post->post_title,
                'name' => $post->post_name,
                'content' => $post->post_content,
                'source' => get_post_meta($post->ID, 'source', false),
                'lang' => get_post_meta($post->ID, 'lang', false),
                'remoteID' => get_post_meta($post->ID, 'remoteID', false),
                'remoteChanged' => get_post_meta($post->ID, 'remoteChanged', false),
                'category' => wp_get_post_terms($post->ID, 'rrze_' . $type . '_category', ['fields' => 'ids']),
                'tag' => wp_get_post_terms($post->ID, 'rrze_' . $type . '_tag', ['fields' => 'ids']),
            ];
        }

        return $snapshot;
    }

    /**
     * Build one newer remote entry with terms unique to that entry.
     *
     * @return array<string, mixed>
     */
    private function remoteEntry(string $type, int $remoteId): array
    {
        return [
            'id' => $remoteId,
            'source' => 'website',
            'remoteID' => $remoteId,
            'remoteChanged' => 200,
            'title' => ['rendered' => 'Changed entry ' . $remoteId],
            'content' => ['rendered' => '<p>Changed content</p>'],
            'lang' => 'en',
            'link' => self::SOURCE_URL . '/' . $type . '/' . $remoteId,
            'rrze_' . $type . '_category' => ['New category ' . $remoteId],
            'rrze_' . $type . '_tag' => ['New tag ' . $remoteId],
        ];
    }

    /**
     * Keep all HTTP offline while supplying one complete remote collection.
     *
     * @param array<int, array<string, mixed>> $entries Source snapshot.
     */
    private function mockSnapshot(string $type, array $entries): void
    {
        /**
         * Answer only the fixture's own source endpoint.
         *
         * @param mixed $preempt Existing short-circuit response.
         * @param array<string, mixed> $arguments Request options.
         * @param string $url Requested URL.
         * @return array<string, mixed>|WP_Error
         */
        $callback = static function ($preempt, array $arguments, string $url) use ($type, $entries) {
            if (!str_starts_with($url, self::SOURCE_URL . '/wp-json/wp/v2/' . $type . '?')) {
                return new WP_Error('unexpected_assignment_http', 'Only the offline sync source is allowed.');
            }

            return [
                'headers' => ['x-wp-totalpages' => '1'],
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode($entries),
                'cookies' => [],
            ];
        };
        $this->addTrackedFilter('pre_http_request', $callback, 3);
    }

    /**
     * Reject exactly one real INSERT or DELETE without mocking the term API.
     *
     * WordPress can return success even when these SQL statements fail. The
     * missing column forces the actual test database to reject the write; the
     * immediately following relationship hook records its database error.
     *
     * @param int $skipMatches Relationship writes allowed before fault injection.
     * @return \stdClass Mutable evidence containing injection count and errors.
     */
    private function failRelationshipWrites(
        string $type,
        string $kind,
        int $remoteId,
        string $operation,
        int $skipMatches = 0
    ): \stdClass {
        $state = (object) ['armed' => false, 'matches' => 0, 'injected' => 0, 'errors' => []];
        $taxonomy = 'rrze_' . $type . '_' . $kind;
        $beforeHook = $operation === 'INSERT' ? 'add_term_relationship' : 'delete_term_relationships';
        $afterHook = $operation === 'INSERT' ? 'added_term_relationship' : 'deleted_term_relationships';

        /**
         * Arm the fault only for the selected entry and taxonomy relationship.
         *
         * @param int $postId Entry receiving a relationship change.
         * @param int|array<int, int|string> $termTaxonomyIds Changed relationships.
         * @param string $changedTaxonomy Taxonomy receiving the change.
         */
        $arm = static function (int $postId, $termTaxonomyIds, string $changedTaxonomy) use (
            $state,
            $taxonomy,
            $remoteId,
            $skipMatches
        ): void {
            if ($changedTaxonomy !== $taxonomy || (int) get_post_meta($postId, 'remoteID', true) !== $remoteId) {
                return;
            }
            $state->matches++;
            $state->armed = $state->injected === 0 && $state->matches > $skipMatches;
        };

        /**
         * Force the armed relationship write to fail in the real test database.
         *
         * @param string $query SQL statement about to execute.
         * @return string Original statement or a statement with an invalid column.
         */
        $reject = static function (string $query) use ($state, $operation): string {
            $table = $GLOBALS['wpdb']->term_relationships;
            $prefix = $operation === 'INSERT' ? 'INSERT INTO `' . $table . '` ' : 'DELETE FROM ' . $table . ' ';
            if (!$state->armed || !str_starts_with($query, $prefix)) {
                return $query;
            }
            $state->armed = false;
            $state->injected++;

            return str_replace('term_taxonomy_id', 'rrze_answers_missing_term_id', $query);
        };

        /**
         * Capture the database failure before another query clears last_error.
         */
        $observe = static function () use ($state): void {
            if ($state->injected > count($state->errors)) {
                $state->errors[] = $GLOBALS['wpdb']->last_error;
            }
        };
        $this->addTrackedFilter($beforeHook, $arm, 3);
        $this->addTrackedFilter('query', $reject, 1);
        $this->addTrackedFilter($afterHook, $observe, 0);

        return $state;
    }

    /**
     * Register a test callback with unconditional teardown cleanup.
     *
     * @param callable $callback WordPress filter or action callback.
     */
    private function addTrackedFilter(string $hook, callable $callback, int $acceptedArguments): void
    {
        add_filter($hook, $callback, 10, $acceptedArguments);
        $this->filters[] = [$hook, $callback];
    }

    /**
     * Invoke the same public boundary used by cron.
     *
     * @return array<string, mixed>|WP_Error
     */
    private function synchronize(string $type)
    {
        return (new SyncAPI())->setEntries($type, self::SOURCE, 'all', self::SOURCE_URL);
    }
}
