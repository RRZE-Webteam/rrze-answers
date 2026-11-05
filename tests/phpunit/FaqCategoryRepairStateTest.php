<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Migrations\FaqCategoryRepairState;
use RuntimeException;
use WP_UnitTestCase;

/** Verify cheap repair gates and ownership-fenced per-site recovery storage. */
final class FaqCategoryRepairStateTest extends WP_UnitTestCase
{
    private static int $secondarySiteId = 0;

    /** @var callable[] Fault-injection callbacks removed before test rollback. */
    private array $queryFilters = [];

    /**
     * Create real secondary-site tables before per-test temporary-table filters.
     *
     * MySQL temporary tables cannot participate twice in the same fenced option
     * statement. Ordinary WordPress site tables have no such restriction.
     *
     * @param \WP_UnitTest_Factory $factory Factory bound to the isolated test DB.
     * @return void
     */
    public static function wpSetUpBeforeClass(\WP_UnitTest_Factory $factory): void
    {
        $siteId = $factory->blog->create();
        if (is_wp_error($siteId)) {
            throw new RuntimeException('Could not create the repair-state test site.');
        }
        self::$secondarySiteId = (int) $siteId;
    }

    /**
     * Remove the class fixture and its real tables from the isolated test database.
     *
     * @return void
     */
    public static function wpTearDownAfterClass(): void
    {
        if (self::$secondarySiteId > 0) {
            $deleted = wp_delete_site(self::$secondarySiteId);
            if (is_wp_error($deleted)) {
                throw new RuntimeException('Could not remove the repair-state test site.');
            }
            self::$secondarySiteId = 0;
        }
    }

    /**
     * Start each case without bootstrap-created repair state.
     *
     * @return void
     */
    public function set_up(): void
    {
        parent::set_up();
        $this->clearManagedOptions();
    }

    /**
     * Remove query interception before WordPress restores the isolated test data.
     *
     * @return void
     */
    public function tear_down(): void
    {
        foreach ($this->queryFilters as $filter) {
            remove_filter('query', $filter, 10);
        }
        parent::tear_down();
    }

    /**
     * Completed sites need no query after WordPress has loaded autoloaded options.
     *
     * @return void
     */
    public function testCompletedGateUsesExistingAutoloadCache(): void
    {
        global $wpdb;

        $state = new FaqCategoryRepairState();
        self::assertTrue($state->shouldRun());
        self::assertTrue($state->acquire());
        $state->setStatus('done');
        $state->release();
        self::assertSame('on', $this->autoloadValue(FaqCategoryRepairState::STATE_OPTION));

        wp_load_alloptions();
        $queries = $wpdb->num_queries;
        self::assertFalse($state->shouldRun());
        self::assertFalse($state->acquire());
        self::assertSame($queries, $wpdb->num_queries);
    }

    /**
     * Retry gates wait until their deadline and newer state is not downgraded.
     *
     * @return void
     */
    public function testRetryAndNewerVersionGatesPreventAttempts(): void
    {
        $state = new FaqCategoryRepairState();
        self::assertTrue($state->acquire());
        $state->setStatus('retry', time() + FaqCategoryRepairState::RETRY_DELAY);
        $state->release();
        self::assertFalse($state->shouldRun());

        update_option(FaqCategoryRepairState::STATE_OPTION, [
            'version' => FaqCategoryRepairState::VERSION,
            'status' => 'retry',
            'next_retry' => time() - 1,
        ], true);
        self::assertTrue($state->shouldRun());

        update_option(FaqCategoryRepairState::STATE_OPTION, [
            'version' => FaqCategoryRepairState::VERSION + 1,
            'status' => 'running',
            'next_retry' => 0,
        ], true);
        self::assertFalse($state->shouldRun());
    }

    /**
     * A second contender cannot overwrite a busy lease or its recovery details.
     *
     * @return void
     */
    public function testBusyLeaseHasOnlyOneOwner(): void
    {
        $first = new FaqCategoryRepairState();
        $second = new FaqCategoryRepairState();
        self::assertTrue($first->acquire());
        $first->saveDetails(['pending' => ['term_ids' => [1404, 1409]]]);
        self::assertFalse($second->acquire());
        $first->assertOwned();
        self::assertSame(['pending' => ['term_ids' => [1404, 1409]]], $first->readDetails());
        self::assertSame('off', $this->autoloadValue(FaqCategoryRepairState::DETAIL_OPTION));
        self::assertSame('off', $this->autoloadValue(FaqCategoryRepairState::LOCK_OPTION));

        $first->release();
        self::assertTrue($second->acquire());
        $second->clearDetails();
        self::assertSame([], $second->readDetails());
        $second->release();
    }

    /**
     * An expired lease can be replaced without losing the crash-recovery payload.
     *
     * @return void
     */
    public function testExpiredLeasePreservesPendingDetailsForNextOwner(): void
    {
        add_option(FaqCategoryRepairState::LOCK_OPTION, (time() - 60) . ':crashed-owner', '', false);
        add_option(FaqCategoryRepairState::DETAIL_OPTION, ['pending' => [1404, 1409]], '', false);
        $state = new FaqCategoryRepairState();
        self::assertTrue($state->acquire());
        self::assertGreaterThan(time(), $state->expiresAt());
        self::assertSame(['pending' => [1404, 1409]], $state->readDetails());
        $state->assertOwned();
        $state->release();
    }

    /**
     * Recheck a completed database gate even when the current cache says pending.
     *
     * @return void
     */
    public function testAcquisitionRechecksGateWithoutTrustingCachedValue(): void
    {
        global $wpdb;

        $pending = ['version' => 1, 'status' => 'retry', 'next_retry' => 0];
        add_option(FaqCategoryRepairState::STATE_OPTION, $pending, '', true);
        wp_load_alloptions();
        $wpdb->update($wpdb->options, [
            'option_value' => maybe_serialize(['version' => 1, 'status' => 'done', 'next_retry' => 0]),
        ], ['option_name' => FaqCategoryRepairState::STATE_OPTION]);

        $state = new FaqCategoryRepairState();
        self::assertTrue($state->shouldRun(), 'This request intentionally retains an older gate cache.');
        self::assertFalse($state->acquire());
        self::assertNull($wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            FaqCategoryRepairState::LOCK_OPTION
        )));
    }

    /**
     * Fence writes even when ownership changes after the preliminary ownership read.
     *
     * @dataProvider ownedMutations
     * @param string $operation Public mutator to interrupt immediately before its SQL.
     * @return void
     */
    public function testWritesRejectOwnershipLostImmediatelyBeforeSql(string $operation): void
    {
        global $wpdb;

        $state = new FaqCategoryRepairState();
        self::assertTrue($state->acquire());
        $state->saveDetails(['pending' => [1404]]);
        $state->setStatus('running', $state->expiresAt());
        $originalGate = get_option(FaqCategoryRepairState::STATE_OPTION);
        $option = $operation === 'setStatus'
            ? FaqCategoryRepairState::STATE_OPTION
            : FaqCategoryRepairState::DETAIL_OPTION;
        $newOwner = (time() + FaqCategoryRepairState::LOCK_TTL) . ':replacement-owner';
        $intercepted = false;
        $filter =
            /**
             * Replace the lease in the narrow read/write race window.
             *
             * @param string $sql WordPress SQL statement about to execute.
             * @return string The unchanged original statement, now fenced out.
             */
            static function (string $sql) use ($wpdb, $option, $newOwner, &$intercepted): string {
                if (!$intercepted && str_contains($sql, $option)
                    && (str_starts_with($sql, 'INSERT INTO ') || str_starts_with($sql, 'DELETE repair_details '))) {
                    $intercepted = true;
                    $wpdb->update($wpdb->options, ['option_value' => $newOwner], [
                        'option_name' => FaqCategoryRepairState::LOCK_OPTION,
                    ]);
                }
                return $sql;
            };
        $this->queryFilters[] = $filter;
        add_filter('query', $filter, 10);

        try {
            if ($operation === 'saveDetails') {
                $state->saveDetails(['pending' => ['must-not-overwrite']]);
            } elseif ($operation === 'setStatus') {
                $state->setStatus('done');
            } else {
                $state->clearDetails();
            }
            self::fail('A stale owner must not report its write as successful.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('lease', $error->getMessage());
        }
        self::assertTrue($intercepted);
        self::assertSame(['pending' => [1404]], $state->readDetails());
        self::assertSame($originalGate, get_option(FaqCategoryRepairState::STATE_OPTION));

        $state->release();
        self::assertSame($newOwner, $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            FaqCategoryRepairState::LOCK_OPTION
        )), 'Stale cleanup must preserve the replacement lease.');
    }

    /**
     * Exercise each persisted-state mutation at its ownership race boundary.
     *
     * @return array<string, array{string}>
     */
    public static function ownedMutations(): array
    {
        return [
            'recovery details' => ['saveDetails'],
            'completion gate' => ['setStatus'],
            'journal cleanup' => ['clearDetails'],
        ];
    }

    /**
     * A failed database write cannot be mistaken for a durable recovery journal.
     *
     * @return void
     */
    public function testDatabaseFailurePreservesPreviousDetailsAndDoesNotPrint(): void
    {
        $state = new FaqCategoryRepairState();
        self::assertTrue($state->acquire());
        $state->saveDetails(['pending' => [1404]]);
        $injected = false;
        $filter =
            /**
             * Fail only the next intended detail write using an invalid SQL column.
             *
             * @param string $sql WordPress SQL statement about to execute.
             * @return string Fault-injected statement or untouched SQL.
             */
            static function (string $sql) use (&$injected): string {
                if (!$injected && str_starts_with($sql, 'INSERT INTO ')
                    && str_contains($sql, FaqCategoryRepairState::DETAIL_OPTION)) {
                    $injected = true;
                    return str_replace('(option_name, option_value, autoload)', '(missing_repair_column, option_value, autoload)', $sql);
                }
                return $sql;
            };
        $this->queryFilters[] = $filter;
        add_filter('query', $filter, 10);

        ob_start();
        try {
            $state->saveDetails(['pending' => ['must-not-persist']]);
            self::fail('A failed SQL write must throw.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('database', $error->getMessage());
        } finally {
            $output = ob_get_clean();
        }
        self::assertSame('', $output);
        self::assertTrue($injected);
        self::assertSame(['pending' => [1404]], $state->readDetails());
        self::assertNotSame('done', get_option(FaqCategoryRepairState::STATE_OPTION, [])['status'] ?? null);
        $state->release();
    }

    /**
     * A failed cooldown write leaves the running deadline and lease available.
     *
     * @return void
     */
    public function testFailedRetryGateWritePreservesRunningGateAndLease(): void
    {
        $state = new FaqCategoryRepairState();
        self::assertTrue($state->acquire());
        $state->setStatus('running', $state->expiresAt());
        $originalGate = get_option(FaqCategoryRepairState::STATE_OPTION);
        $injected = false;
        $filter =
            /**
             * Reject only the intended retry-gate SQL write.
             *
             * @param string $sql WordPress SQL statement about to execute.
             * @return string Fault-injected statement or untouched SQL.
             */
            static function (string $sql) use (&$injected): string {
                if (!$injected && str_starts_with($sql, 'INSERT INTO ')
                    && str_contains($sql, "'" . FaqCategoryRepairState::STATE_OPTION . "'")) {
                    $injected = true;
                    return str_replace('(option_name, option_value, autoload)', '(missing_repair_column, option_value, autoload)', $sql);
                }
                return $sql;
            };
        $this->queryFilters[] = $filter;
        add_filter('query', $filter, 10);

        try {
            $state->setStatus('retry', time() + FaqCategoryRepairState::RETRY_DELAY);
            self::fail('A rejected retry gate must not report success.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('database', $error->getMessage());
        }
        self::assertTrue($injected);
        self::assertSame($originalGate, get_option(FaqCategoryRepairState::STATE_OPTION));
        self::assertFalse((new FaqCategoryRepairState())->shouldRun());
        $state->assertOwned();
        $state->release();
    }

    /**
     * Completion and recovery data stay separate even within one multisite request.
     *
     * @return void
     */
    public function testLeaseRejectsSiteSwitchAndOtherSiteHasIndependentState(): void
    {
        $first = new FaqCategoryRepairState();
        self::assertTrue($first->acquire());
        $first->saveDetails(['site' => 'first']);

        switch_to_blog(self::$secondarySiteId);
        try {
            try {
                $first->saveDetails(['site' => 'wrong']);
                self::fail('An acquired lease must not follow a site switch.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('another site', $error->getMessage());
            }

            $second = new FaqCategoryRepairState();
            self::assertTrue($second->shouldRun());
            self::assertTrue($second->acquire());
            self::assertSame([], $second->readDetails());
            $second->saveDetails(['site' => 'second']);
            $second->setStatus('done');
            $second->release();
            self::assertFalse($second->shouldRun());
        } finally {
            restore_current_blog();
        }

        self::assertSame(['site' => 'first'], $first->readDetails());
        self::assertTrue($first->shouldRun());
        $first->clearDetails();
        $first->release();
    }

    /**
     * Inspect the durable autoload setting independently of option caching.
     *
     * @param string $name Managed option name.
     * @return string Database autoload value.
     */
    private function autoloadValue(string $name): string
    {
        global $wpdb;

        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
            $name
        ));
    }

    /**
     * Clear only the migration's three options in the disposable test database.
     *
     * @return void
     */
    private function clearManagedOptions(): void
    {
        foreach ([
            FaqCategoryRepairState::STATE_OPTION,
            FaqCategoryRepairState::DETAIL_OPTION,
            FaqCategoryRepairState::LOCK_OPTION,
        ] as $name) {
            delete_option($name);
        }
    }
}
