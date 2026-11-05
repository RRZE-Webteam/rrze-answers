<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Migrations;

use RuntimeException;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Keep one site's category repair gate, recovery details and expiring DB lease.
 *
 * Only the small gate is autoloaded. Every mutation is fenced by the exact lease
 * value in the same SQL statement, so an expired owner cannot overwrite a later
 * attempt. Lock operations intentionally avoid add_option()'s upsert behavior.
 */
final class FaqCategoryRepairState
{
    public const VERSION = 1;
    public const STATE_OPTION = 'rrze_answers_faq_category_repair';
    public const DETAIL_OPTION = 'rrze_answers_faq_category_repair_details';
    public const LOCK_OPTION = 'rrze_answers_faq_category_repair_lock';
    public const LOCK_TTL = 600;
    public const RETRY_DELAY = 86400;

    private ?int $blogId = null;
    private string $optionsTable = '';
    private ?string $lockValue = null;
    private int $leaseExpiresAt = 0;

    /**
     * Read only the cached gate on ordinary requests after completion or failure.
     *
     * @return bool Whether this version may attempt repair now.
     */
    public function shouldRun(): bool
    {
        $this->assertSite();
        return $this->isRunnable(get_option(self::STATE_OPTION, []), time());
    }

    /**
     * Acquire an atomic per-site lease, taking over only an expired exact value.
     *
     * Recheck the gate directly after acquisition because another request may
     * have completed while this request retained an older alloptions cache.
     *
     * @return bool Whether this instance owns a lease for an eligible attempt.
     * @throws RuntimeException On database errors or invalid site switches.
     */
    public function acquire(): bool
    {
        global $wpdb;

        if ($this->lockValue !== null) {
            $this->assertOwned();
            return true;
        }
        if (!$this->shouldRun()) {
            return false;
        }

        $table = $wpdb->options;
        $blogId = get_current_blog_id();
        $expiresAt = $this->databaseNow() + self::LOCK_TTL;
        $value = $expiresAt . ':' . wp_generate_uuid4();
        $insert = $wpdb->prepare(
            "INSERT IGNORE INTO {$table} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
            self::LOCK_OPTION,
            $value
        );

        if ($this->execute($insert) !== 1) {
            $existing = $this->readOptionRow(self::LOCK_OPTION);
            if ($existing !== null) {
                $deleted = $this->execute($wpdb->prepare(
                    "DELETE FROM {$table} WHERE option_name = %s AND option_value = %s"
                    . " AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) <= UNIX_TIMESTAMP()",
                    self::LOCK_OPTION,
                    $existing['option_value']
                ));
                if ($deleted !== 1) {
                    return false;
                }
                $this->clearOptionCache(self::LOCK_OPTION);
            }

            // A concurrent contender can win after stale-lock removal.
            if ($this->execute($insert) !== 1) {
                return false;
            }
        }

        $this->blogId = $blogId;
        $this->optionsTable = $table;
        $this->lockValue = $value;
        $this->leaseExpiresAt = $expiresAt;
        $this->clearOptionCache(self::LOCK_OPTION);

        try {
            $gate = $this->readOptionRow(self::STATE_OPTION);
            $state = $gate === null ? [] : maybe_unserialize($gate['option_value']);
            if (!$this->isRunnable($state, $this->databaseNow())) {
                $this->release();
                return false;
            }
            $this->assertOwned();
        } catch (Throwable $error) {
            try {
                $this->release();
            } catch (Throwable $releaseError) {
                // Preserve the original failure; an unreleased lease expires.
            }
            throw $error;
        }

        return true;
    }

    /**
     * Read recovery details directly, without trusting an option-object cache.
     *
     * @return array<string, mixed> The stored payload, or an empty array if absent.
     * @throws RuntimeException On unreadable or malformed details.
     */
    public function readDetails(): array
    {
        $row = $this->readOptionRow(self::DETAIL_OPTION);
        if ($row === null) {
            return [];
        }

        $details = maybe_unserialize($row['option_value']);
        if (!is_array($details)) {
            throw new RuntimeException('FAQ category repair details are malformed.');
        }
        return $details;
    }

    /**
     * Durably save recovery or diagnostic data before taxonomy rows are changed.
     *
     * @param array<string, mixed> $details Serializable recovery payload.
     * @return void
     * @throws RuntimeException On write failure or lost ownership.
     */
    public function saveDetails(array $details): void
    {
        $this->writeOwnedOption(self::DETAIL_OPTION, $details, 'off');
    }

    /**
     * Save the compact gate without invoking the plugin's settings-save hooks.
     *
     * @param string $status One of running, retry, or done.
     * @param int $nextRetry Earliest Unix timestamp for another attempt.
     * @return void
     * @throws RuntimeException On invalid state, write failure or lost ownership.
     */
    public function setStatus(string $status, int $nextRetry = 0): void
    {
        if (!in_array($status, ['running', 'retry', 'done'], true) || $nextRetry < 0) {
            throw new RuntimeException('Invalid FAQ category repair state.');
        }
        $this->writeOwnedOption(self::STATE_OPTION, [
            'version' => self::VERSION,
            'status' => $status,
            'next_retry' => $status === 'done' ? 0 : $nextRetry,
        ], 'on');
    }

    /**
     * Remove finalized recovery details only while still owning this site's lease.
     *
     * @return void
     * @throws RuntimeException On write failure or lost ownership.
     */
    public function clearDetails(): void
    {
        global $wpdb;

        $this->assertOwned();
        $this->execute($wpdb->prepare(
            "DELETE repair_details FROM {$this->optionsTable} AS repair_details"
            . " INNER JOIN {$this->optionsTable} AS repair_lock ON " . $this->lockCondition()
            . ' WHERE repair_details.option_name = %s',
            self::DETAIL_OPTION
        ));
        $this->clearOptionCache(self::DETAIL_OPTION);
        $this->assertOwned();
        if ($this->readOptionRow(self::DETAIL_OPTION) !== null) {
            throw new RuntimeException('Could not clear FAQ category repair details.');
        }
    }

    /**
     * Release only this instance's exact lease, including an expired own lease.
     *
     * A replaced or missing lease is harmless during cleanup and is left alone.
     *
     * @return void
     * @throws RuntimeException On SQL failure or a site switch while holding a lease.
     */
    public function release(): void
    {
        global $wpdb;

        if ($this->lockValue === null) {
            return;
        }
        $this->assertSite();
        $this->execute($wpdb->prepare(
            "DELETE FROM {$this->optionsTable} WHERE option_name = %s AND option_value = %s",
            self::LOCK_OPTION,
            $this->lockValue
        ));
        $this->clearOptionCache(self::LOCK_OPTION);
        $this->lockValue = null;
        $this->blogId = null;
        $this->optionsTable = '';
        $this->leaseExpiresAt = 0;
    }

    /**
     * Build the SQL predicate used to fence an UPDATE joined to the lock row.
     *
     * @param string $alias SQL alias of the current site's options-table lock row.
     * @return string Prepared SQL predicate, without the ON or WHERE keyword.
     * @throws RuntimeException If no lease is held or the alias/site is invalid.
     */
    public function lockCondition(string $alias = 'repair_lock'): string
    {
        global $wpdb;

        $this->assertSite();
        if ($this->lockValue === null || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $alias)) {
            throw new RuntimeException('No valid FAQ category repair lease is available.');
        }
        return $wpdb->prepare(
            "{$alias}.option_name = %s AND {$alias}.option_value = %s"
            . " AND CAST(SUBSTRING_INDEX({$alias}.option_value, ':', 1) AS UNSIGNED) > UNIX_TIMESTAMP()",
            self::LOCK_OPTION,
            $this->lockValue
        );
    }

    /**
     * Return the held lease deadline for the running gate's inexpensive cooldown.
     *
     * @return int Unix timestamp, or zero when this instance has no lease.
     */
    public function expiresAt(): int
    {
        $this->assertSite();
        return $this->leaseExpiresAt;
    }

    /**
     * Verify the live database lease before work or after a fenced no-op write.
     *
     * @return void
     * @throws RuntimeException If another owner took over, time expired, or SQL failed.
     */
    public function assertOwned(): void
    {
        $condition = $this->lockCondition();
        if ($this->execute("SELECT 1 FROM {$this->optionsTable} AS repair_lock WHERE {$condition}") !== 1) {
            throw new RuntimeException('FAQ category repair lease was lost or expired.');
        }
    }

    /**
     * Interpret only this migration version while leaving newer versions alone.
     *
     * @param mixed $state Stored option value.
     * @param int $now Current Unix timestamp.
     * @return bool Whether an attempt may run.
     */
    private function isRunnable($state, int $now): bool
    {
        if (!is_array($state)) {
            return true;
        }
        $version = (int) ($state['version'] ?? 0);
        if ($version > self::VERSION) {
            return false;
        }
        if ($version === self::VERSION) {
            if (($state['status'] ?? '') === 'done') {
                return false;
            }
            if ((int) ($state['next_retry'] ?? 0) > $now) {
                return false;
            }
        }
        return true;
    }

    /**
     * Persist an option using one lease-fenced statement and verify its exact value.
     *
     * @param string $name Managed option name.
     * @param array<string, mixed> $value Serializable option value.
     * @param string $autoload Explicit on/off autoload value.
     * @return void
     * @throws RuntimeException On SQL failure, lost ownership or failed verification.
     */
    private function writeOwnedOption(string $name, array $value, string $autoload): void
    {
        global $wpdb;

        $this->assertOwned();
        $serialized = maybe_serialize($value);
        $this->execute($wpdb->prepare(
            "INSERT INTO {$this->optionsTable} (option_name, option_value, autoload)"
            . " SELECT %s, %s, %s FROM {$this->optionsTable} AS repair_lock WHERE " . $this->lockCondition()
            . ' ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)',
            $name,
            $serialized,
            $autoload
        ));
        $this->clearOptionCache($name);
        $this->assertOwned();
        $row = $this->readOptionRow($name);
        if ($row === null || $row['option_value'] !== $serialized || $row['autoload'] !== $autoload) {
            throw new RuntimeException('Could not persist FAQ category repair state.');
        }
    }

    /**
     * Read an option through the database and distinguish absence from SQL failure.
     *
     * @param string $name Managed option name.
     * @return array{option_value: string, autoload: string}|null Stored row or absence.
     * @throws RuntimeException On SQL failure or a site switch while holding a lease.
     */
    private function readOptionRow(string $name): ?array
    {
        global $wpdb;

        $this->assertSite();
        $count = $this->execute($wpdb->prepare(
            "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $name
        ));
        if ($count === 0) {
            return null;
        }
        return [
            'option_value' => (string) $wpdb->get_var(null, 0),
            'autoload' => (string) $wpdb->get_var(null, 1),
        ];
    }

    /**
     * Use the database clock for lease creation and ownership checks.
     *
     * @return int Current database Unix timestamp.
     * @throws RuntimeException On SQL failure or an invalid clock response.
     */
    private function databaseNow(): int
    {
        global $wpdb;

        $this->execute('SELECT UNIX_TIMESTAMP()');
        $now = (int) $wpdb->get_var();
        if ($now <= 0) {
            throw new RuntimeException('Could not read the FAQ category repair lease clock.');
        }
        return $now;
    }

    /**
     * Execute SQL without emitting database diagnostics into frontend responses.
     *
     * @param string $sql Prepared statement.
     * @return int Number of selected or affected rows.
     * @throws RuntimeException On a database error.
     */
    private function execute(string $sql): int
    {
        global $wpdb;

        $previous = $wpdb->suppress_errors(true);
        try {
            $result = $wpdb->query($sql);
            if ($result === false) {
                throw new RuntimeException('FAQ category repair database operation failed.');
            }
            return (int) $result;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    /**
     * Refuse to reuse an acquired lease after switching WordPress sites.
     *
     * @return void
     * @throws RuntimeException If the blog ID or options table changed.
     */
    private function assertSite(): void
    {
        global $wpdb;

        if ($this->blogId !== null
            && ($this->blogId !== get_current_blog_id() || $this->optionsTable !== $wpdb->options)) {
            throw new RuntimeException('FAQ category repair lease belongs to another site.');
        }
    }

    /**
     * Keep WordPress option and missing-option caches coherent after direct writes.
     *
     * @param string $name Changed option name.
     * @return void
     */
    private function clearOptionCache(string $name): void
    {
        $this->assertSite();
        wp_cache_delete($name, 'options');
        if ($name === self::STATE_OPTION) {
            wp_cache_delete('alloptions', 'options');
        }
        $notOptions = wp_cache_get('notoptions', 'options');
        if (is_array($notOptions) && isset($notOptions[$name])) {
            unset($notOptions[$name]);
            wp_cache_set('notoptions', $notOptions, 'options');
        }
    }
}
