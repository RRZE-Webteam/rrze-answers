<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Migrations\FaqCategoryHierarchyRepair;
use WP_UnitTestCase;

/** Verify the local, site-scoped repair of partially migrated FAQ category trees. */
final class FaqCategoryHierarchyRepairTest extends WP_UnitTestCase
{
    private const LEGACY = 'faq_category';
    private const CURRENT = 'rrze_faq_category';
    private const STATE = 'rrze_answers_faq_category_repair';
    private const DETAILS = 'rrze_answers_faq_category_repair_details';
    private const LOCK = 'rrze_answers_faq_category_repair_lock';

    /** @var int Secondary site with real tables, created before test transactions. */
    private static int $otherSite = 0;

    /** @var array<int, array{string, callable}> Fault-injection hooks to remove. */
    private array $filters = [];

    /** @var int[] Temporary super administrators to revoke. */
    private array $superAdministrators = [];

    private bool $previousErrorSuppression;
    private int $administrator;

    /**
     * Create real secondary-site tables so MySQL can self-join the guarded UPDATE.
     *
     * @param \WP_UnitTest_Factory $factory WordPress fixture factory.
     * @return void
     */
    public static function wpSetUpBeforeClass(\WP_UnitTest_Factory $factory): void
    {
        $siteId = $factory->blog->create();
        self::assertIsInt($siteId);
        self::$otherSite = $siteId;
    }

    /**
     * Remove the real secondary-site tables after all transactional fixtures end.
     *
     * @return void
     */
    public static function wpTearDownAfterClass(): void
    {
        if (self::$otherSite !== 0) {
            self::assertFalse(is_wp_error(wp_delete_site(self::$otherSite)));
            self::$otherSite = 0;
        }
    }

    /**
     * Isolate each scenario from earlier migration state and admin requests.
     *
     * @return void
     */
    public function set_up(): void
    {
        parent::set_up();
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $this->previousErrorSuppression = $GLOBALS['wpdb']->suppress_errors(true);
        $this->clearRepairState();
        register_taxonomy(self::LEGACY, ['rrze_faq'], ['hierarchical' => true]);
        $this->administrator = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->administrator);
        set_current_screen('front');
    }

    /**
     * Restore hooks and request context before WordPress rolls back the fixture.
     *
     * @return void
     */
    public function tear_down(): void
    {
        foreach ($this->filters as [$hook, $callback]) {
            remove_filter($hook, $callback, 10);
        }
        foreach ($this->superAdministrators as $userId) {
            revoke_super_admin($userId);
        }
        if (taxonomy_exists(self::LEGACY)) {
            unregister_taxonomy(self::LEGACY);
        }
        if (taxonomy_exists('repair_other_category')) {
            unregister_taxonomy('repair_other_category');
        }
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        set_current_screen('front');
        $GLOBALS['wpdb']->suppress_errors($this->previousErrorSuppression);
        parent::tear_down();
    }

    /**
     * Restore the production-shaped tree without rewriting its 67 published FAQs.
     *
     * @return void
     */
    public function testProductionShapeRestoresAllRowsAndPreservesStoredContent(): void
    {
        $fixture = $this->createProductionShape();
        add_option('rrze_faq_cpt_update_done', 1);
        add_option('rrze_migrate_domains_done', 1);
        $settings = ['registeredDomains' => ['anleitungen' => 'https://www.anleitungen.rrze.fau.de/']];
        delete_option('rrze-answers');
        add_option('rrze-answers', $settings);
        unregister_taxonomy(self::LEGACY);

        self::assertCount(24, get_terms(['taxonomy' => self::CURRENT, 'hide_empty' => false]));
        self::assertSame([$fixture['visible_root']['term_id']], $this->visibleCategoryIds());
        $before = $this->snapshotTables();
        $expected = $before;
        foreach ($expected['term_taxonomy'] as &$row) {
            if (in_array((int) $row['term_id'], $fixture['parent_ids'], true)) {
                $row['taxonomy'] = self::CURRENT;
            }
        }
        unset($row);

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertSame($expected, $this->snapshotTables(), 'Only the two parent taxonomy names may change.');
        self::assertCount(26, $this->visibleCategoryIds());
        self::assertCount(36, $this->taxonomyRows(self::LEGACY));
        self::assertSame($settings, get_option('rrze-answers'));
        self::assertSame('1', (string) get_option('rrze_faq_cpt_update_done'));
        self::assertSame('1', (string) get_option('rrze_migrate_domains_done'));
        self::assertCount(67, get_posts(['post_type' => 'rrze_faq', 'post_status' => 'publish', 'numberposts' => -1]));
        foreach ($fixture['post_ids'] as $postId) {
            self::assertSame('website', get_post_meta($postId, 'source', true));
        }
    }

    /**
     * Include all required old ancestors while retaining unrelated old categories.
     *
     * @return void
     */
    public function testDeepAncestorsAndRepeatedReferencesAreRepairedOnce(): void
    {
        $root = $this->createTerm('Ancestor root', self::LEGACY);
        $parent = $this->createTerm('Ancestor parent', self::LEGACY, $root['term_id']);
        $first = $this->createTerm('First migrated child', self::CURRENT, $parent['term_id']);
        $this->createTerm('Second migrated child', self::CURRENT, $parent['term_id']);
        $this->createTerm('Current grandchild', self::CURRENT, $first['term_id']);
        $unrelated = $this->createTerm('Unrelated legacy root', self::LEGACY);
        unregister_taxonomy(self::LEGACY);

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertSame(self::CURRENT, $this->rowFor($root['term_taxonomy_id'])['taxonomy']);
        self::assertSame(self::CURRENT, $this->rowFor($parent['term_taxonomy_id'])['taxonomy']);
        self::assertSame(self::LEGACY, $this->rowFor($unrelated['term_taxonomy_id'])['taxonomy']);
        self::assertCount(5, $this->visibleCategoryIds());
    }

    /**
     * Leave every category untouched when any ancestor chain is ambiguous or unsafe.
     *
     * @dataProvider conflictCases
     * @param string $scenario Corruption to add alongside a separately valid branch.
     * @return void
     */
    public function testConflictStopsTheWholeSite(string $scenario): void
    {
        $this->createPair('Valid branch');
        $pair = $this->createPair('Conflicting branch');
        $db = $GLOBALS['wpdb'];

        if ($scenario === 'missing_parent') {
            $db->update($db->term_taxonomy, ['parent' => 2147000000], ['term_taxonomy_id' => $pair['child']['term_taxonomy_id']]);
        } elseif ($scenario === 'missing_term_row') {
            $db->delete($db->terms, ['term_id' => $pair['parent']['term_id']]);
        } elseif ($scenario === 'cycle') {
            $db->update($db->term_taxonomy, ['parent' => $pair['child']['term_id']], ['term_taxonomy_id' => $pair['parent']['term_taxonomy_id']]);
        } elseif ($scenario === 'self_parent') {
            $db->update($db->term_taxonomy, ['parent' => $pair['parent']['term_id']], ['term_taxonomy_id' => $pair['parent']['term_taxonomy_id']]);
        } elseif ($scenario === 'slug_collision') {
            $target = $this->createTerm('Existing target root', self::CURRENT);
            $slug = $db->get_var($db->prepare("SELECT slug FROM {$db->terms} WHERE term_id = %d", $target['term_id']));
            $db->update($db->terms, ['slug' => $slug], ['term_id' => $pair['parent']['term_id']]);
        } elseif ($scenario === 'shared_identity') {
            register_taxonomy('repair_other_category', ['post']);
            $db->insert($db->term_taxonomy, [
                'term_id' => $pair['parent']['term_id'],
                'taxonomy' => 'repair_other_category',
                'description' => '',
                'parent' => 0,
                'count' => 0,
            ]);
        } elseif ($scenario === 'unselected_legacy_child') {
            $this->createTerm('Unselected legacy child', self::LEGACY, $pair['parent']['term_id']);
        } elseif ($scenario === 'foreign_parent_taxonomy') {
            $db->update($db->term_taxonomy, ['taxonomy' => 'category'], ['term_taxonomy_id' => $pair['parent']['term_taxonomy_id']]);
        } else {
            $postId = $scenario === 'dangling_relationship'
                ? 2147000000
                : self::factory()->post->create(['post_type' => $scenario === 'legacy_post' ? 'faq' : 'post']);
            $db->insert($db->term_relationships, [
                'object_id' => $postId,
                'term_taxonomy_id' => $pair['parent']['term_taxonomy_id'],
                'term_order' => 0,
            ]);
        }
        unregister_taxonomy(self::LEGACY);
        $before = $this->snapshotTables();

        (new FaqCategoryHierarchyRepair())->run();

        self::assertSame($before, $this->snapshotTables());
        $this->assertRetryScheduled();
        $details = get_option(self::DETAILS);
        self::assertIsArray($details);
        self::assertNotEmpty($details['error'] ?? '');
    }

    /**
     * Cover data loss and hierarchy risks without depending on error-message text.
     *
     * @return array<string, array{string}>
     */
    public static function conflictCases(): array
    {
        return [
            'missing parent' => ['missing_parent'],
            'missing term row' => ['missing_term_row'],
            'mixed taxonomy cycle' => ['cycle'],
            'self-referencing parent' => ['self_parent'],
            'existing target slug' => ['slug_collision'],
            'shared term identity' => ['shared_identity'],
            'unmigrated sibling' => ['unselected_legacy_child'],
            'another parent taxonomy' => ['foreign_parent_taxonomy'],
            'legacy FAQ relationship' => ['legacy_post'],
            'other post type relationship' => ['other_post'],
            'missing post relationship' => ['dangling_relationship'],
        ];
    }

    /**
     * Recount existing FAQ relationships and invalidate old and new cached results.
     *
     * @return void
     */
    public function testRelatedCurrentFaqsKeepTheirLinksAndReceiveAccurateCounts(): void
    {
        $pair = $this->createPair('Cached branch');
        $published = $this->createFaq('Published direct parent FAQ');
        $draft = $this->createFaq('Draft direct parent FAQ', 'draft');
        $db = $GLOBALS['wpdb'];
        foreach ([$published, $draft] as $postId) {
            wp_set_object_terms($postId, [$pair['parent']['term_id']], self::LEGACY);
            self::assertFalse(get_the_terms($postId, self::CURRENT));
            self::assertIsArray(get_the_terms($postId, self::LEGACY));
        }
        $db->update($db->term_taxonomy, ['count' => 999], ['term_taxonomy_id' => $pair['parent']['term_taxonomy_id']]);
        get_term($pair['parent']['term_id'], self::LEGACY);
        get_terms(['taxonomy' => self::CURRENT, 'hide_empty' => false]);
        update_option(self::LEGACY . '_children', [$pair['parent']['term_id'] => [$pair['child']['term_id']]]);
        unregister_taxonomy(self::LEGACY);
        $relationships = $this->snapshotTables()['term_relationships'];
        wp_cache_set('faq_repair_unrelated', 'preserved', 'rrze_answers_test');

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertSame('1', (string) $this->rowFor($pair['parent']['term_taxonomy_id'])['count']);
        self::assertSame($relationships, $this->snapshotTables()['term_relationships']);
        self::assertFalse(get_option(self::LEGACY . '_children'));
        self::assertSame('preserved', wp_cache_get('faq_repair_unrelated', 'rrze_answers_test'));
        foreach ([$published, $draft] as $postId) {
            $terms = get_the_terms($postId, self::CURRENT);
            self::assertIsArray($terms);
            self::assertSame([$pair['parent']['term_id']], array_column($terms, 'term_id'));
            self::assertFalse(wp_cache_get($postId, self::LEGACY . '_relationships'));
        }
        self::assertSame(self::CURRENT, get_term($pair['parent']['term_id'], self::CURRENT)->taxonomy);
    }

    /**
     * Reject unsupported count policies before changing even the first parent row.
     *
     * @dataProvider unsupportedCountPolicies
     * @param string $policy Foreign taxonomy customization to apply.
     * @return void
     */
    public function testUnsupportedCountPolicyStopsBeforeTaxonomySql(string $policy): void
    {
        $this->createPair('Unsupported count policy');
        unregister_taxonomy(self::LEGACY);
        $taxonomy = get_taxonomy(self::CURRENT);
        self::assertNotFalse($taxonomy);
        $originalCallback = $taxonomy->update_count_callback;
        $originalTypes = $taxonomy->object_type;
        if ($policy === 'callback') {
            $taxonomy->update_count_callback = '__return_null';
        } elseif ($policy === 'object_types') {
            $taxonomy->object_type[] = 'post';
        } else {
            $this->trackFilter(
                'update_post_term_count_statuses',
                /**
                 * Simulate an integration returning an invalid status-list member.
                 *
                 * @return array{string, bool} Invalid count policy.
                 */
                static function (): array {
                    return ['publish', false];
                }
            );
        }
        $writes = [];
        $this->trackFilter(
            'query',
            /**
             * Observe taxonomy UPDATEs so a compensating write cannot hide early mutation.
             *
             * @param string $query SQL about to execute.
             * @return string Unchanged SQL.
             */
            static function (string $query) use (&$writes): string {
                if (preg_match('/^\s*UPDATE\b/i', $query) && str_contains($query, $GLOBALS['wpdb']->term_taxonomy)) {
                    $writes[] = $query;
                }
                return $query;
            }
        );
        $before = $this->snapshotTables();

        try {
            (new FaqCategoryHierarchyRepair())->run();
        } finally {
            $taxonomy->update_count_callback = $originalCallback;
            $taxonomy->object_type = $originalTypes;
        }

        self::assertSame([], $writes);
        self::assertSame($before, $this->snapshotTables());
        $this->assertRetryScheduled();
    }

    /**
     * Cover incompatible callback, object types and filtered post statuses.
     *
     * @return array<string, array{string}>
     */
    public static function unsupportedCountPolicies(): array
    {
        return [
            'custom count callback' => ['callback'],
            'additional object type' => ['object_types'],
            'invalid filtered status' => ['status_filter'],
        ];
    }

    /**
     * Detect case-only editor changes before an otherwise collation-equal SQL update.
     *
     * @dataProvider caseOnlyEditorChanges
     * @param string $field Stored text field changed after the recovery journal was saved.
     * @return void
     */
    public function testCaseOnlyConcurrentEditStopsTheWholeTaxonomyMove(string $field): void
    {
        $this->createPair('First case-sensitive branch');
        $pair = $this->createPair('Second case-sensitive branch');
        unregister_taxonomy(self::LEGACY);
        $before = $this->snapshotTables();
        $expected = $before;
        $tableProperty = $field === 'description' ? 'term_taxonomy' : 'terms';
        $idColumn = $field === 'description' ? 'term_taxonomy_id' : 'term_id';
        $id = $pair['parent'][$idColumn];
        $changedValue = '';
        foreach ($expected[$tableProperty] as &$row) {
            if ((int) $row[$idColumn] === $id) {
                $changedValue = strtoupper($row[$field]);
                self::assertNotSame($row[$field], $changedValue);
                $row[$field] = $changedValue;
            }
        }
        unset($row);
        $evidence = (object) ['injected' => 0];
        $this->trackFilter(
            'query',
            /**
             * Change one stored field immediately before the migration's guarded UPDATE.
             *
             * @param string $query SQL about to execute.
             * @return string Unchanged migration SQL after the concurrent edit.
             */
            static function (string $query) use ($tableProperty, $idColumn, $id, $field, $changedValue, $evidence): string {
                $db = $GLOBALS['wpdb'];
                if ($evidence->injected === 0 && preg_match('/^\s*UPDATE\b/i', $query)
                    && str_contains($query, $db->term_taxonomy) && preg_match('/SET\s+destination\.taxonomy\s*=/i', $query)) {
                    $evidence->injected++;
                    $db->update($db->$tableProperty, [$field => $changedValue], [$idColumn => $id]);
                }
                return $query;
            }
        );

        (new FaqCategoryHierarchyRepair())->run();

        self::assertSame(1, $evidence->injected);
        self::assertSame($expected, $this->snapshotTables(), 'Only the simulated editor change may persist.');
        self::assertCount(2, $this->taxonomyRows(self::LEGACY));
        $this->assertRetryScheduled();
    }

    /**
     * Cover every preserved text field used to fence the category snapshot.
     *
     * @return array<string, array{string}>
     */
    public static function caseOnlyEditorChanges(): array
    {
        return ['name case' => ['name'], 'slug case' => ['slug'], 'description case' => ['description']];
    }

    /**
     * Retain a durable plan before SQL and retry a failed atomic category update.
     *
     * @return void
     */
    public function testFailedTaxonomyWriteDoesNotPartiallyConvertTheTree(): void
    {
        $this->createPair('First SQL branch');
        $this->createPair('Second SQL branch');
        unregister_taxonomy(self::LEGACY);
        $before = $this->snapshotTables();
        $evidence = (object) ['injected' => 0, 'pending' => null];
        $filter =
            /**
             * Reject the first taxonomy-changing statement after recording its recovery plan.
             *
             * @param string $query SQL about to execute.
             * @return string Original query or a guaranteed failing statement.
             */
            static function (string $query) use ($evidence): string {
                $table = $GLOBALS['wpdb']->term_taxonomy;
                if ($evidence->injected === 0 && preg_match('/^\s*UPDATE\b/i', $query)
                    && str_contains($query, $table) && str_contains($query, self::CURRENT)) {
                    $evidence->injected++;
                    $evidence->pending = get_option(self::DETAILS);
                    return str_replace($table, $table . '_forced_missing', $query);
                }
                return $query;
            };
        $this->trackFilter('query', $filter);

        (new FaqCategoryHierarchyRepair())->run();

        self::assertSame(1, $evidence->injected);
        self::assertIsArray($evidence->pending);
        self::assertCount(2, $evidence->pending['rows']);
        self::assertSame($before, $this->snapshotTables());
        $this->assertRetryScheduled();
        remove_filter('query', $filter, 10);
        $this->allowImmediateRetry();

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertCount(4, $this->taxonomyRows(self::CURRENT));
        self::assertSame([], $this->taxonomyRows(self::LEGACY));
    }

    /**
     * Finish cache cleanup after a crash that happened after the taxonomy UPDATE.
     *
     * @return void
     */
    public function testPendingConvertedParentsAreRecoveredBeforeAnEmptyFreshScan(): void
    {
        $pair = $this->createPair('Interrupted branch');
        $postId = $this->createFaq('Interrupted parent relationship');
        wp_set_object_terms($postId, [$pair['parent']['term_id']], self::LEGACY);
        self::assertFalse(get_the_terms($postId, self::CURRENT));
        get_term($pair['parent']['term_id'], self::LEGACY);
        get_terms(['taxonomy' => self::CURRENT, 'hide_empty' => false]);
        update_option(self::LEGACY . '_children', [$pair['parent']['term_id'] => [$pair['child']['term_id']]]);
        $pending = $this->pendingRow($pair['parent']['term_taxonomy_id']);
        unregister_taxonomy(self::LEGACY);
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['taxonomy' => self::CURRENT], [
            'term_taxonomy_id' => $pair['parent']['term_taxonomy_id'],
        ]);
        add_option(self::STATE, ['version' => 1, 'status' => 'running', 'next_retry' => 0], '', false);
        add_option(self::DETAILS, ['rows' => [$pending], 'object_ids' => [$postId]], '', false);

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertFalse(get_option(self::LEGACY . '_children'));
        self::assertCount(2, get_terms(['taxonomy' => self::CURRENT, 'hide_empty' => false]));
        $terms = get_the_terms($postId, self::CURRENT);
        self::assertIsArray($terms);
        self::assertSame([$pair['parent']['term_id']], array_column($terms, 'term_id'));
        self::assertCount(2, $this->visibleCategoryIds());
    }

    /**
     * Revalidate and execute a journal persisted immediately before a process stopped.
     *
     * @return void
     */
    public function testPendingLegacyParentsAreRevalidatedAndCompleted(): void
    {
        $first = $this->createPair('First uncommitted branch');
        $second = $this->createPair('Second uncommitted branch');
        $rows = [
            $this->pendingRow($first['parent']['term_taxonomy_id']),
            $this->pendingRow($second['parent']['term_taxonomy_id']),
        ];
        add_option(self::STATE, ['version' => 1, 'status' => 'running', 'next_retry' => 0], '', false);
        add_option(self::DETAILS, ['rows' => $rows, 'object_ids' => []], '', false);
        unregister_taxonomy(self::LEGACY);

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertSame([], $this->taxonomyRows(self::LEGACY));
        self::assertCount(4, $this->visibleCategoryIds());
    }

    /**
     * Refuse to mark done when an external edit reverts categories during final verification.
     *
     * @return void
     */
    public function testFinalVerificationDetectsParentsRevertedToTheLegacyTaxonomy(): void
    {
        $first = $this->createPair('First late-reverted branch');
        $second = $this->createPair('Second late-reverted branch');
        $ids = [$first['parent']['term_taxonomy_id'], $second['parent']['term_taxonomy_id']];
        unregister_taxonomy(self::LEGACY);
        $evidence = (object) ['cacheWritten' => false, 'injected' => 0];
        $filter =
            /**
             * Revert all parents only after hierarchy persistence, just before its final row check.
             *
             * @param string $query SQL about to execute.
             * @return string Unchanged SQL after an intentional concurrent fixture edit.
             */
            static function (string $query) use ($ids, $evidence): string {
                $db = $GLOBALS['wpdb'];
                if (preg_match('/^\s*INSERT\s+INTO\b/i', $query)
                    && str_contains($query, $db->options) && str_contains($query, self::CURRENT . '_children')) {
                    $evidence->cacheWritten = true;
                }
                if ($evidence->cacheWritten && $evidence->injected === 0 && preg_match('/^\s*SELECT\b/i', $query)
                    && str_contains($query, $db->term_taxonomy) && str_contains($query, 'tt.term_taxonomy_id IN')) {
                    $evidence->injected++;
                    $db->query($db->prepare(
                        "UPDATE {$db->term_taxonomy} SET taxonomy = %s WHERE term_taxonomy_id IN (%d, %d)",
                        self::LEGACY,
                        $ids[0],
                        $ids[1]
                    ));
                }
                return $query;
            };
        $this->trackFilter('query', $filter);

        (new FaqCategoryHierarchyRepair())->run();

        self::assertSame(1, $evidence->injected);
        self::assertCount(2, $this->taxonomyRows(self::LEGACY));
        $this->assertRetryScheduled();
        $details = get_option(self::DETAILS);
        self::assertIsArray($details);
        self::assertCount(2, $details['rows']);
        remove_filter('query', $filter, 10);
        $this->allowImmediateRetry();

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
    }

    /**
     * Retain recovery information when SQL succeeded but completion work failed.
     *
     * @dataProvider completionFailureStages
     * @param string $stage Completion statement to reject once.
     * @return void
     */
    public function testCompletionFailureLeavesConvertedParentsRecoverable(string $stage): void
    {
        $pair = $this->createPair('Completion failure branch');
        $postId = $this->createFaq('Completion failure direct FAQ');
        wp_set_object_terms($postId, [$pair['parent']['term_id']], self::LEGACY);
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['count' => 999], [
            'term_taxonomy_id' => $pair['parent']['term_taxonomy_id'],
        ]);
        unregister_taxonomy(self::LEGACY);
        $evidence = (object) ['injected' => 0];
        $filter =
            /**
             * Fail either the count update or durable hierarchy-cache rebuild.
             *
             * @param string $query SQL about to execute.
             * @return string Original SQL or a guaranteed failing statement.
             */
            static function (string $query) use ($stage, $evidence): string {
                $db = $GLOBALS['wpdb'];
                $matches = $stage === 'recount'
                    ? preg_match('/SET\s+tt\.count\s*=/i', $query) && str_contains($query, $db->term_taxonomy)
                    : preg_match('/^\s*INSERT\s+INTO\b/i', $query)
                        && str_contains($query, $db->options) && str_contains($query, self::CURRENT . '_children');
                if ($evidence->injected === 0 && $matches) {
                    $evidence->injected++;
                    $table = $stage === 'recount' ? $db->term_taxonomy : $db->options;
                    return str_replace($table, $table . '_forced_missing', $query);
                }
                return $query;
            };
        $this->trackFilter('query', $filter);
        ob_start();
        try {
            (new FaqCategoryHierarchyRepair())->run();
        } finally {
            $output = ob_get_clean();
        }

        self::assertSame('', $output, 'Repair failures must not leak output into frontend or REST responses.');
        self::assertSame(1, $evidence->injected);
        self::assertSame(self::CURRENT, $this->rowFor($pair['parent']['term_taxonomy_id'])['taxonomy']);
        $this->assertRetryScheduled();
        $details = get_option(self::DETAILS);
        self::assertIsArray($details);
        self::assertCount(1, $details['rows']);
        remove_filter('query', $filter, 10);
        $this->allowImmediateRetry();

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertSame('1', (string) $this->rowFor($pair['parent']['term_taxonomy_id'])['count']);
        self::assertCount(2, $this->visibleCategoryIds());
    }

    /**
     * Cover both completion writes that must succeed before the final version marker.
     *
     * @return array<string, array{string}>
     */
    public static function completionFailureStages(): array
    {
        return ['count update' => ['recount'], 'hierarchy cache' => ['hierarchy']];
    }

    /**
     * Reject a saved plan whose category identity changed during an interruption.
     *
     * @return void
     */
    public function testPendingSnapshotMismatchDoesNotOverwriteAnEditorsChange(): void
    {
        $pair = $this->createPair('Edited during interruption');
        $pending = $this->pendingRow($pair['parent']['term_taxonomy_id']);
        add_option(self::STATE, ['version' => 1, 'status' => 'running', 'next_retry' => 0], '', false);
        add_option(self::DETAILS, ['rows' => [$pending], 'object_ids' => []], '', false);
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->terms, ['slug' => 'changed-by-an-editor'], ['term_id' => $pair['parent']['term_id']]);
        unregister_taxonomy(self::LEGACY);
        $before = $this->snapshotTables();

        (new FaqCategoryHierarchyRepair())->run();

        self::assertSame($before, $this->snapshotTables());
        $this->assertRetryScheduled();
    }

    /**
     * Preserve an unread journal when a database read fails after SQL already committed.
     *
     * @return void
     */
    public function testFailedJournalReadRetainsTheOriginalRecoveryRows(): void
    {
        $pair = $this->createPair('Unread committed journal');
        $details = ['rows' => [$this->pendingRow($pair['parent']['term_taxonomy_id'])], 'object_ids' => []];
        add_option(self::STATE, ['version' => 1, 'status' => 'running', 'next_retry' => 0], '', false);
        add_option(self::DETAILS, $details, '', false);
        update_option(self::LEGACY . '_children', [$pair['parent']['term_id'] => [$pair['child']['term_id']]]);
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['taxonomy' => self::CURRENT], [
            'term_taxonomy_id' => $pair['parent']['term_taxonomy_id'],
        ]);
        unregister_taxonomy(self::LEGACY);
        $evidence = (object) ['injected' => 0];
        $filter =
            /**
             * Reject only the direct recovery-option read once.
             *
             * @param string $query SQL about to execute.
             * @return string Original SQL or a failing read.
             */
            static function (string $query) use ($evidence): string {
                $table = $GLOBALS['wpdb']->options;
                if ($evidence->injected === 0 && preg_match('/^\s*SELECT\b/i', $query)
                    && str_contains($query, $table) && str_contains($query, self::DETAILS)) {
                    $evidence->injected++;
                    return str_replace($table, $table . '_forced_missing', $query);
                }
                return $query;
            };
        $this->trackFilter('query', $filter);

        (new FaqCategoryHierarchyRepair())->run();

        self::assertSame(1, $evidence->injected);
        self::assertSame($details, get_option(self::DETAILS));
        $this->assertRetryScheduled();
        remove_filter('query', $filter, 10);
        $this->allowImmediateRetry();

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertFalse(get_option(self::LEGACY . '_children'));
        self::assertCount(2, $this->visibleCategoryIds());
    }

    /**
     * Restore recovery details when the final success marker cannot be stored.
     *
     * @return void
     */
    public function testFailedDoneWriteRetainsARecoverableCommittedPlan(): void
    {
        $pair = $this->createPair('Failed final marker');
        unregister_taxonomy(self::LEGACY);
        $evidence = (object) ['injected' => 0];
        $filter =
            /**
             * Reject the done gate after successful taxonomy and cache writes.
             *
             * @param string $query SQL about to execute.
             * @return string Original SQL or a failing state write.
             */
            static function (string $query) use ($evidence): string {
                $table = $GLOBALS['wpdb']->options;
                if ($evidence->injected === 0 && preg_match('/^\s*INSERT\s+INTO\b/i', $query)
                    && str_contains($query, $table) && str_contains($query, self::STATE) && str_contains($query, 'done')) {
                    $evidence->injected++;
                    return str_replace($table, $table . '_forced_missing', $query);
                }
                return $query;
            };
        $this->trackFilter('query', $filter);

        (new FaqCategoryHierarchyRepair())->run();

        self::assertSame(1, $evidence->injected);
        self::assertSame(self::CURRENT, $this->rowFor($pair['parent']['term_taxonomy_id'])['taxonomy']);
        $this->assertRetryScheduled();
        $details = get_option(self::DETAILS);
        self::assertIsArray($details);
        self::assertSame([$pair['parent']['term_taxonomy_id']], array_column($details['rows'], 'term_taxonomy_id'));
        remove_filter('query', $filter, 10);
        $this->allowImmediateRetry();

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
    }

    /**
     * Complete harmless sites without converting unrelated legacy categories.
     *
     * @dataProvider healthySiteShapes
     * @param string $shape Category layout that requires no ancestor repair.
     * @return void
     */
    public function testHealthyOrEmptySiteCompletesWithoutTaxonomyChanges(string $shape): void
    {
        if ($shape !== 'empty') {
            $legacy = $this->createTerm('Unrelated legacy root', self::LEGACY);
            $this->createTerm('Unrelated legacy child', self::LEGACY, $legacy['term_id']);
        }
        if ($shape === 'healthy') {
            $current = $this->createTerm('Healthy current root', self::CURRENT);
            $this->createTerm('Healthy current child', self::CURRENT, $current['term_id']);
        }
        unregister_taxonomy(self::LEGACY);
        $before = $this->snapshotTables();
        $writes = [];
        $this->trackFilter(
            'query',
            /**
             * Detect taxonomy writes even if they would later restore the same data.
             *
             * @param string $query SQL about to execute.
             * @return string Unchanged SQL.
             */
            static function (string $query) use (&$writes): string {
                if (preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE)\b/i', $query)
                    && str_contains($query, $GLOBALS['wpdb']->term_taxonomy)) {
                    $writes[] = $query;
                }
                return $query;
            }
        );

        (new FaqCategoryHierarchyRepair())->run();

        $this->assertCompleted();
        self::assertSame([], $writes);
        self::assertSame($before, $this->snapshotTables());
        self::assertCount($shape === 'empty' ? 0 : 2, $this->taxonomyRows(self::LEGACY));
    }

    /**
     * Cover empty sites and valid trees with unreferenced historical categories.
     *
     * @return array<string, array{string}>
     */
    public static function healthySiteShapes(): array
    {
        return ['no FAQ categories' => ['empty'], 'healthy current tree' => ['healthy'], 'legacy categories only' => ['legacy_only']];
    }

    /**
     * Defer repair entirely during installation or suspended cache invalidation.
     *
     * @dataProvider unsafeExecutionContexts
     * @param string $context WordPress lifecycle condition that makes repair unsafe.
     * @return void
     */
    public function testUnsafeExecutionContextCreatesNoStateOrDatabaseWrites(string $context): void
    {
        $this->createPair('Deferred lifecycle branch');
        unregister_taxonomy(self::LEGACY);
        $before = $this->snapshotTables();
        $writes = [];
        $this->trackFilter(
            'query',
            /**
             * Observe every persistent write while the lifecycle guard is active.
             *
             * @param string $query SQL about to execute.
             * @return string Unchanged SQL.
             */
            static function (string $query) use (&$writes): string {
                if (preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $query)) {
                    $writes[] = $query;
                }
                return $query;
            }
        );
        $wasInstalling = wp_installing();
        $wasSuspended = wp_suspend_cache_invalidation(false);
        try {
            if ($context === 'installing') {
                wp_installing(true);
            } else {
                wp_suspend_cache_invalidation(true);
            }
            (new FaqCategoryHierarchyRepair())->run();
        } finally {
            wp_installing($wasInstalling);
            wp_suspend_cache_invalidation($wasSuspended);
        }

        self::assertSame([], $writes);
        self::assertSame($before, $this->snapshotTables());
        foreach ([self::STATE, self::DETAILS, self::LOCK] as $option) {
            self::assertFalse(get_option($option));
        }
    }

    /**
     * Cover both public guards that defer the entire migration lifecycle.
     *
     * @return array<string, array{string}>
     */
    public static function unsafeExecutionContexts(): array
    {
        return ['WordPress installation' => ['installing'], 'cache invalidation suspended' => ['cache_suspended']];
    }

    /**
     * Avoid rescanning category tables on ordinary requests after a successful repair.
     *
     * @return void
     */
    public function testCompletedStateIsIdempotentAndUsesNoTaxonomyScan(): void
    {
        $this->createPair('Idempotent branch');
        unregister_taxonomy(self::LEGACY);
        $repair = new FaqCategoryHierarchyRepair();
        $repair->run();
        $this->assertCompleted();
        $before = $this->snapshotTables();
        $queries = [];
        $filter =
            /**
             * Observe expensive category scans without changing SQL behavior.
             *
             * @param string $query SQL about to execute.
             * @return string Unchanged SQL.
             */
            static function (string $query) use (&$queries): string {
                if (str_contains($query, $GLOBALS['wpdb']->term_taxonomy)) {
                    $queries[] = $query;
                }
                return $query;
            };
        $this->trackFilter('query', $filter);

        $repair->run();

        self::assertSame([], $queries);
        remove_filter('query', $filter, 10);
        self::assertSame($before, $this->snapshotTables());
        $autoload = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
            "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
            self::STATE
        ));
        self::assertContains($autoload, wp_autoload_values_to_autoload());
    }

    /**
     * Keep repair state and changes isolated when one request switches between sites.
     *
     * @return void
     */
    public function testMultisiteRepairsOnlyTheCurrentlySelectedSite(): void
    {
        $mainPair = $this->createPair('Main site branch');
        switch_to_blog(self::$otherSite);
        try {
            $this->clearRepairState();
            $otherPair = $mainPair;
            $db = $GLOBALS['wpdb'];
            foreach (['parent' => self::LEGACY, 'child' => self::CURRENT] as $role => $taxonomy) {
                $identity = $otherPair[$role];
                self::assertSame(1, $db->insert($db->terms, [
                    'term_id' => $identity['term_id'],
                    'name' => 'Other site ' . $role,
                    'slug' => 'other-site-' . $role,
                    'term_group' => 0,
                ]));
                self::assertSame(1, $db->insert($db->term_taxonomy, [
                    'term_taxonomy_id' => $identity['term_taxonomy_id'],
                    'term_id' => $identity['term_id'],
                    'taxonomy' => $taxonomy,
                    'description' => 'Other site description for ' . $role,
                    'parent' => $role === 'child' ? $otherPair['parent']['term_id'] : 0,
                    'count' => 0,
                ]));
                clean_term_cache($identity['term_id'], $taxonomy);
                $stored = $this->rowFor($identity['term_taxonomy_id']);
                self::assertSame($mainPair[$role]['term_id'], (int) $stored['term_id']);
                self::assertSame($mainPair[$role]['term_taxonomy_id'], (int) $stored['term_taxonomy_id']);
            }
            $otherBefore = $this->snapshotTables();
        } finally {
            restore_current_blog();
        }
        unregister_taxonomy(self::LEGACY);
        $repair = new FaqCategoryHierarchyRepair();

        $repair->run();

        $this->assertCompleted();
        self::assertSame(self::CURRENT, $this->rowFor($mainPair['parent']['term_taxonomy_id'])['taxonomy']);
        switch_to_blog(self::$otherSite);
        try {
            self::assertSame($otherBefore, $this->snapshotTables());
            self::assertFalse(get_option(self::STATE));
            $repair->run();
            $this->assertCompleted();
            self::assertSame(self::CURRENT, $this->rowFor($otherPair['parent']['term_taxonomy_id'])['taxonomy']);
        } finally {
            restore_current_blog();
        }
        $this->assertCompleted();
    }

    /**
     * Restrict failure details to super administrators in the affected site's backend.
     *
     * @return void
     */
    public function testFailureNoticeIsLimitedToAffectedSiteSuperAdministrators(): void
    {
        $pair = $this->createPair('Notice branch');
        $this->createTerm('Unselected old child', self::LEGACY, $pair['parent']['term_id']);
        unregister_taxonomy(self::LEGACY);
        $repair = new FaqCategoryHierarchyRepair();
        $repair->run();
        $this->assertRetryScheduled();
        set_current_screen('edit-rrze_faq_category');
        self::assertFalse(is_super_admin($this->administrator));
        self::assertSame('', $this->renderNotice($repair));
        grant_super_admin($this->administrator);
        $this->superAdministrators[] = $this->administrator;
        self::assertStringContainsString('notice', $this->renderNotice($repair));
        set_current_screen('front');
        self::assertSame('', $this->renderNotice($repair));
        set_current_screen('dashboard-network');
        self::assertSame('', $this->renderNotice($repair));
        switch_to_blog(self::$otherSite);
        try {
            $this->clearRepairState();
            set_current_screen('edit-rrze_faq_category');
            self::assertSame('', $this->renderNotice($repair));
        } finally {
            restore_current_blog();
        }
    }

    /**
     * Block external HTTP at the transport boundary while retaining existing mocks.
     *
     * @return void
     */
    public function testOfflineGuardBlocksUnmockedExternalRequests(): void
    {
        $response = wp_remote_get('https://must-not-be-requested.example/faq-repair');
        self::assertWPError($response);
        self::assertSame('rrze_answers_test_external_http_blocked', $response->get_error_code());
        $mock = ['response' => ['code' => 200], 'body' => 'local fixture'];
        self::assertSame($mock, \rrze_answers_tests_block_external_http($mock, [], 'https://fixture.example/'));
        self::assertFalse(\rrze_answers_tests_block_external_http(false, [], 'http://elements.multisite.localhost:8890/'));
    }

    /**
     * Create the observed category shape with synthetic text and the same counts.
     *
     * @return array{parent_ids: int[], visible_root: array{term_id: int, term_taxonomy_id: int}, post_ids: int[]}
     */
    private function createProductionShape(): array
    {
        $studyRoot = $this->createTerm('Legacy studies root', self::LEGACY);
        $itRoot = $this->createTerm('Legacy IT root', self::LEGACY);
        $visible = $this->createTerm('FAQ: IT', self::CURRENT);
        $postIds = [];
        foreach (['Pruefungen' => 4, 'Bachelorarbeit' => 5, 'Studium' => 11, 'Praktikum' => 12] as $name => $count) {
            $term = $this->createTerm('FAQ: ' . $name, self::CURRENT, $studyRoot['term_id']);
            for ($index = 0; $index < $count; $index++) {
                $postId = $this->createFaq($name . ' question ' . $index);
                wp_set_object_terms($postId, [$term['term_id']], self::CURRENT);
                $postIds[] = $postId;
            }
        }
        $itPosts = [];
        for ($index = 0; $index < 35; $index++) {
            $postId = $this->createFaq('IT question ' . $index);
            wp_set_object_terms($postId, [$visible['term_id']], self::CURRENT);
            $itPosts[] = $postId;
            $postIds[] = $postId;
        }
        $itTerms = [];
        $counts = [2, 5, 4, 4, 1, 2, 3, 3, 5, 3, 3, 8, 1, 1, 1, 1];
        foreach ($counts as $index => $count) {
            $term = $this->createTerm('IT category ' . $index, self::CURRENT, $itRoot['term_id']);
            $itTerms[] = $term;
            foreach (array_slice($itPosts, 0, $count) as $postId) {
                wp_set_object_terms($postId, [$term['term_id']], self::CURRENT, true);
            }
        }
        foreach ([3 => 2, 1 => 3, 10 => 1] as $parentIndex => $count) {
            $term = $this->createTerm('IT nested category ' . $parentIndex, self::CURRENT, $itTerms[$parentIndex]['term_id']);
            foreach (array_slice($itPosts, 0, $count) as $postId) {
                wp_set_object_terms($postId, [$term['term_id']], self::CURRENT, true);
            }
        }
        for ($index = 0; $index < 36; $index++) {
            $this->createTerm('Unrelated old category ' . $index, self::LEGACY);
        }
        $this->createTerm('Unrelated current FAQ tag', 'rrze_faq_tag');
        $this->createTerm('Unrelated glossary category', 'rrze_glossary_category');
        update_term_meta($studyRoot['term_id'], 'linked_page', 700001);
        update_term_meta($itRoot['term_id'], 'source', 'website');
        update_term_meta($itRoot['term_id'], 'lang', 'de');

        return ['parent_ids' => [$studyRoot['term_id'], $itRoot['term_id']], 'visible_root' => $visible, 'post_ids' => $postIds];
    }

    /**
     * Create one migrated child whose real parent remains in the old taxonomy.
     *
     * @param string $name Unique branch label.
     * @return array{parent: array{term_id: int, term_taxonomy_id: int}, child: array{term_id: int, term_taxonomy_id: int}}
     */
    private function createPair(string $name): array
    {
        $parent = $this->createTerm($name . ' parent', self::LEGACY);
        $child = $this->createTerm($name . ' child', self::CURRENT, $parent['term_id']);
        return compact('parent', 'child');
    }

    /**
     * Store a category with an optional historical cross-taxonomy parent reference.
     *
     * @param string $name Unique term name.
     * @param string $taxonomy Taxonomy containing the new term.
     * @param int $parent Preserved parent term ID, even across taxonomies.
     * @return array{term_id: int, term_taxonomy_id: int} New identity pair.
     */
    private function createTerm(string $name, string $taxonomy, int $parent = 0): array
    {
        $term = wp_insert_term($name, $taxonomy, ['description' => 'Description for ' . $name]);
        self::assertIsArray($term);
        $identity = ['term_id' => (int) $term['term_id'], 'term_taxonomy_id' => (int) $term['term_taxonomy_id']];
        if ($parent !== 0) {
            self::assertSame(1, $GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['parent' => $parent], [
                'term_taxonomy_id' => $identity['term_taxonomy_id'],
            ]));
            clean_term_cache($identity['term_id'], $taxonomy);
        }
        return $identity;
    }

    /**
     * Store a synthetic FAQ without including production personal or content data.
     *
     * @param string $title Fixture question.
     * @param string $status WordPress publication status.
     * @return int New FAQ post ID.
     */
    private function createFaq(string $title, string $status = 'publish'): int
    {
        return self::factory()->post->create([
            'post_type' => 'rrze_faq',
            'post_status' => $status,
            'post_title' => $title,
            'post_content' => '<!-- wp:paragraph --><p>Unchanged local answer.</p><!-- /wp:paragraph -->',
            'meta_input' => ['source' => 'website', 'lang' => 'de'],
        ]);
    }

    /**
     * Read the real hierarchical category list with pagination disabled for inspection.
     *
     * @return int[] Term IDs actually emitted as administrative table rows.
     */
    private function visibleCategoryIds(): array
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-terms-list-table.php';
        $_REQUEST = ['taxonomy' => self::CURRENT, 'post_type' => 'rrze_faq'];
        set_current_screen('edit-' . self::CURRENT);
        $screen = get_current_screen();
        $screen->taxonomy = self::CURRENT;
        $screen->post_type = 'rrze_faq';
        update_user_option($this->administrator, 'edit_' . self::CURRENT . '_per_page', 100);
        $table = new \WP_Terms_List_Table(['screen' => $screen]);
        $table->prepare_items();
        ob_start();
        try {
            $table->display_rows_or_placeholder();
        } finally {
            $html = (string) ob_get_clean();
        }
        preg_match_all('/<tr\b[^>]*\bid="tag-(\d+)"/', $html, $matches);
        return array_map('intval', $matches[1]);
    }

    /**
     * Capture all content-bearing tables while intentionally excluding repair options.
     *
     * @return array<string, array<int, array<string, string|null>>> Raw stable snapshots.
     */
    private function snapshotTables(): array
    {
        $db = $GLOBALS['wpdb'];
        $snapshots = [];
        foreach (['terms' => 'term_id', 'term_taxonomy' => 'term_taxonomy_id', 'term_relationships' => 'object_id, term_taxonomy_id',
            'termmeta' => 'meta_id', 'posts' => 'ID', 'postmeta' => 'meta_id'] as $property => $order) {
            $table = $db->$property;
            $snapshots[$property] = $db->get_results("SELECT * FROM {$table} ORDER BY {$order}", ARRAY_A);
        }
        return $snapshots;
    }

    /**
     * Read a taxonomy directly so unregistered legacy terms remain observable.
     *
     * @param string $taxonomy Taxonomy identifier.
     * @return array<int, array<string, string>> Stored taxonomy rows.
     */
    private function taxonomyRows(string $taxonomy): array
    {
        $db = $GLOBALS['wpdb'];
        return $db->get_results($db->prepare("SELECT * FROM {$db->term_taxonomy} WHERE taxonomy = %s ORDER BY term_taxonomy_id", $taxonomy), ARRAY_A);
    }

    /**
     * Read one stable term-taxonomy identity without consulting caches.
     *
     * @param int $termTaxonomyId Stable taxonomy row ID.
     * @return array<string, string> Raw taxonomy row.
     */
    private function rowFor(int $termTaxonomyId): array
    {
        $db = $GLOBALS['wpdb'];
        $row = $db->get_row($db->prepare("SELECT * FROM {$db->term_taxonomy} WHERE term_taxonomy_id = %d", $termTaxonomyId), ARRAY_A);
        self::assertIsArray($row);
        return $row;
    }

    /**
     * Build the durable identity snapshot used to simulate an interrupted migration.
     *
     * @param int $termTaxonomyId Stable legacy taxonomy row ID.
     * @return array<string, int|string> Pending recovery snapshot.
     */
    private function pendingRow(int $termTaxonomyId): array
    {
        $db = $GLOBALS['wpdb'];
        $row = $db->get_row($db->prepare(
            "SELECT t.term_id, tt.term_taxonomy_id, tt.parent, tt.count, tt.taxonomy, t.name, t.slug, tt.description
             FROM {$db->terms} t JOIN {$db->term_taxonomy} tt ON tt.term_id = t.term_id
             WHERE tt.term_taxonomy_id = %d",
            $termTaxonomyId
        ), ARRAY_A);
        self::assertIsArray($row);
        foreach (['term_id', 'term_taxonomy_id', 'parent', 'count'] as $field) {
            $row[$field] = (int) $row[$field];
        }
        return $row;
    }

    /**
     * Assert that the site has a completed version marker and no remaining plan.
     *
     * @return void
     */
    private function assertCompleted(): void
    {
        $state = get_option(self::STATE);
        self::assertIsArray($state);
        self::assertSame(1, $state['version']);
        self::assertSame('done', $state['status']);
        self::assertFalse(get_option(self::DETAILS));
        self::assertFalse(get_option(self::LOCK));
    }

    /**
     * Assert that a failed site remains eligible for a delayed retry.
     *
     * @return void
     */
    private function assertRetryScheduled(): void
    {
        $state = get_option(self::STATE);
        self::assertIsArray($state);
        self::assertSame('retry', $state['status']);
        self::assertGreaterThan(time(), $state['next_retry']);
        $autoload = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
            "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
            self::DETAILS
        ));
        self::assertNotContains($autoload, wp_autoload_values_to_autoload());
    }

    /**
     * Advance only the isolated fixture's retry schedule after fault removal.
     *
     * @return void
     */
    private function allowImmediateRetry(): void
    {
        $state = get_option(self::STATE);
        self::assertIsArray($state);
        $state['next_retry'] = 0;
        update_option(self::STATE, $state, false);
    }

    /**
     * Remove per-site state so new fixtures exercise their first update request.
     *
     * @return void
     */
    private function clearRepairState(): void
    {
        foreach ([self::STATE, self::DETAILS, self::LOCK] as $name) {
            delete_option($name);
        }
    }

    /**
     * Track a temporary SQL callback for unconditional cleanup on test failure.
     *
     * @param string $hook Filter name.
     * @param callable $callback Temporary callback.
     * @return void
     */
    private function trackFilter(string $hook, callable $callback): void
    {
        add_filter($hook, $callback, 10, 1);
        $this->filters[] = [$hook, $callback];
    }

    /**
     * Capture the public notice output for the current user and selected site.
     *
     * @param FaqCategoryHierarchyRepair $repair Public migration boundary.
     * @return string Rendered administrator notice.
     */
    private function renderNotice(FaqCategoryHierarchyRepair $repair): string
    {
        ob_start();
        try {
            $repair->renderNotice();
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
