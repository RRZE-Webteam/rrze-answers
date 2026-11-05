<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Migrations;

defined('ABSPATH') || exit;

use RuntimeException;
use Throwable;

/**
 * Recover legacy ancestors omitted by the former FAQ plugin's joined migration.
 *
 * Only the current site's required ancestors move. The durable journal bridges
 * the SQL/cache boundary without starting or committing a caller's transaction.
 */
final class FaqCategoryHierarchyRepair
{
    private const LEGACY = 'faq_category';
    private const TARGET = 'rrze_faq_category';

    /**
     * Attempt one site-local repair, keeping failures out of public responses.
     *
     * @return void
     */
    public function run(): void
    {
        global $_wp_suspend_cache_invalidation;

        if (wp_installing() || !taxonomy_exists(self::TARGET) || !empty($_wp_suspend_cache_invalidation)) {
            return;
        }

        $state = new FaqCategoryRepairState();
        $locked = false;
        $releaseLease = true;
        $details = [];
        $detailsLoaded = false;

        try {
            if (!$state->shouldRun() || !$state->acquire()) {
                return;
            }
            $locked = true;
            $details = $state->readDetails();
            $detailsLoaded = true;
            $state->setStatus('running', $state->expiresAt());

            // Resume before discovery: committed rows no longer look like old parents.
            if (!empty($details['rows'])) {
                $currentTaxonomy = $this->journalTaxonomy($details);
                if ($currentTaxonomy === self::TARGET) {
                    $this->finish($details, $state);
                    return;
                }
            }

            $plan = $this->inspect();
            if (!empty($details['rows']) && $this->taxonomyIds($details['rows']) !== $this->taxonomyIds($plan['rows'])) {
                throw new RuntimeException('The pending FAQ parent selection changed; manual inspection is required.');
            }
            if ($plan['rows'] === []) {
                $state->clearDetails();
                $state->setStatus('done');
                return;
            }

            // An unsupported count policy must stop before changing categories.
            $this->countStatuses();
            $plan['object_ids'] = array_values(array_unique(array_merge($details['object_ids'] ?? [], $plan['object_ids'])));
            $details = $plan;
            $state->saveDetails($details);
            if ($state->readDetails() !== $details) {
                throw new RuntimeException('The FAQ repair journal could not be verified.');
            }

            $this->moveAncestors($details['rows'], $state);
            $this->finish($details, $state);
        } catch (Throwable $exception) {
            if ($locked) {
                // A storage failure must not turn into an HTML/PHP error in REST.
                if ($detailsLoaded) {
                    try {
                        $details['error'] = substr($exception->getMessage(), 0, 1000);
                        $details['code'] = 'faq_category_repair_failed';
                        $state->saveDetails($details);
                    } catch (Throwable $storageException) {
                        // The existing journal/lease remains available for recovery.
                    }
                }
                try {
                    $state->setStatus('retry', time() + FaqCategoryRepairState::RETRY_DELAY);
                } catch (Throwable $storageException) {
                    // Keep the lease until expiry if even the retry gate cannot
                    // be stored; otherwise every request could retry immediately.
                    $releaseLease = false;
                }
            }
        } finally {
            if ($locked && $releaseLease) {
                try {
                    $state->release();
                } catch (Throwable $storageException) {
                    // An unreleased lease expires; never mask the original failure.
                }
            }
        }
    }

    /**
     * Explain a failed repair only to superadmins visiting the affected subsite.
     *
     * @return void
     */
    public function renderNotice(): void
    {
        if (!is_admin() || is_network_admin() || !is_super_admin()) {
            return;
        }
        $gate = get_option(FaqCategoryRepairState::STATE_OPTION, []);
        if (!is_array($gate) || ($gate['status'] ?? '') !== 'retry') {
            return;
        }
        $details = get_option(FaqCategoryRepairState::DETAIL_OPTION, []);
        $reason = is_array($details) && is_string($details['error'] ?? null) ? $details['error'] : '';
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('RRZE Answers could not complete the automatic FAQ category repair. Please inspect the category hierarchy. A new attempt will be made after the retry interval.', 'rrze-answers')
            . ($reason !== '' ? ' ' . esc_html($reason) : '')
            . '</p></div>';
    }

    /**
     * Derive and validate the complete ancestor closure using unfiltered DB rows.
     *
     * @return array{rows: array<int, array<string, mixed>>, object_ids: int[]}
     */
    private function inspect(): array
    {
        global $wpdb;

        $rows = $this->selectRows($wpdb->prepare('tt.taxonomy IN (%s, %s)', self::LEGACY, self::TARGET));
        $current = [];
        $legacy = [];
        foreach ($rows as $row) {
            if ($row['taxonomy'] === self::TARGET) {
                $current[$row['term_id']] = $row;
            } else {
                $legacy[$row['term_id']] = $row;
            }
        }

        $candidates = [];
        $visited = [];
        foreach ($current as $start) {
            $id = $start['term_id'];
            $path = [];
            while ($id !== 0 && !isset($visited[$id])) {
                if (isset($path[$id])) {
                    throw new RuntimeException('A cycle was found in the FAQ category parents at term ' . $id . '.');
                }
                $path[$id] = true;
                $row = $current[$id] ?? $legacy[$id] ?? null;
                if ($row === null || $row['name'] === null || $row['slug'] === null) {
                    throw new RuntimeException('FAQ parent term ' . $id . ' is missing or has no terms row.');
                }
                if ($row['taxonomy'] === self::LEGACY) {
                    $candidates[$row['term_taxonomy_id']] = $row;
                }
                $id = $row['parent'];
            }
            $visited += $path;
        }

        if ($candidates === []) {
            return ['rows' => [], 'object_ids' => []];
        }
        ksort($candidates, SORT_NUMERIC);
        $termIds = array_column($candidates, 'term_id');
        $candidateIds = array_fill_keys($termIds, true);
        foreach ($legacy as $row) {
            if (isset($candidateIds[$row['parent']]) && !isset($candidateIds[$row['term_id']])) {
                throw new RuntimeException('Legacy child ' . $row['term_id'] . ' would lose its parent ' . $row['parent'] . '.');
            }
        }

        // Compare slugs using the database's collation, not PHP string equality.
        $ids = $this->idList($termIds);
        $conflicts = $this->queryRows(
            "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ($ids) GROUP BY term_id HAVING COUNT(*) > 1 LIMIT 1"
        );
        if ($conflicts !== []) {
            throw new RuntimeException('FAQ parent ' . $conflicts[0]['term_id'] . ' has an ambiguous shared term identity.');
        }
        $conflicts = $this->queryRows($wpdb->prepare(
            "SELECT candidate.term_id, other.term_id AS other_id, tt.taxonomy
             FROM {$wpdb->terms} candidate
             INNER JOIN {$wpdb->terms} other ON other.slug = candidate.slug AND other.term_id <> candidate.term_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = other.term_id
             WHERE candidate.term_id IN ($ids)
               AND (tt.taxonomy = %s OR (tt.taxonomy = %s AND tt.term_id IN ($ids)))",
            self::TARGET,
            self::LEGACY
        ));
        $candidates = array_values($candidates);
        if ($conflicts !== []) {
            $candidates = $this->assignTargetSlugs($candidates, $conflicts);
        }
        return ['rows' => $candidates, 'object_ids' => $this->linkedObjects($candidates)];
    }

    /**
     * Preserve existing target slugs and rename only conflicting legacy ancestors.
     *
     * Among colliding candidates, the first taxonomy row keeps the old slug unless
     * a current FAQ category already owns it. New slugs are checked globally and
     * against this plan using the terms column's actual database collation.
     *
     * @param array<int, array<string, mixed>> $rows Ordered legacy candidates.
     * @param array<int, array<string, mixed>> $conflicts Colliding term pairs.
     * @return array<int, array<string, mixed>> Original snapshots with optional target_slug.
     */
    private function assignTargetSlugs(array $rows, array $conflicts): array
    {
        global $wpdb;

        $this->requireTransactionalTermTables();
        $positions = array_flip(array_column($rows, 'term_id'));
        $rename = [];
        foreach ($conflicts as $conflict) {
            if ($conflict['taxonomy'] === self::TARGET
                || $positions[(int) $conflict['other_id']] < $positions[(int) $conflict['term_id']]) {
                $rename[(int) $conflict['term_id']] = true;
            }
        }
        $metadata = $this->queryRows($wpdb->prepare(
            "SELECT COLLATION(slug) AS collation_name, CHARSET(slug) AS charset_name
             FROM {$wpdb->terms} WHERE term_id = %d", $rows[0]['term_id']
        ));
        $collation = $metadata[0]['collation_name'] ?? '';
        $charset = $metadata[0]['charset_name'] ?? '';
        if (!preg_match('/^[a-z0-9_]+$/D', $collation) || !preg_match('/^[a-z0-9_]+$/D', $charset)) {
            throw new RuntimeException('The FAQ slug database collation could not be determined.');
        }
        $reserved = [];
        foreach ($rows as &$row) {
            if (!isset($rename[$row['term_id']])) {
                continue;
            }
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $suffix = '-legacy-' . $row['term_id'] . ($attempt === 0 ? '' : '-' . ($attempt + 1));
                $slug = _truncate_post_slug($row['slug'], 200 - strlen($suffix)) . $suffix;
                $occupied = $this->queryRows($wpdb->prepare(
                    "SELECT term_id FROM {$wpdb->terms} WHERE slug = %s AND term_id <> %d LIMIT 1",
                    $slug, $row['term_id']
                ));
                if ($occupied !== []) {
                    continue;
                }
                if ($reserved !== []) {
                    $matches = $this->queryRows($wpdb->prepare(
                        "SELECT 1 AS collision WHERE CONVERT(%s USING $charset) COLLATE $collation IN ("
                        . implode(', ', array_fill(0, count($reserved), '%s')) . ')',
                        $slug, ...$reserved
                    ));
                    if ($matches !== []) {
                        continue;
                    }
                }
                $row['target_slug'] = $slug;
                $reserved[] = $slug;
                break;
            }
            if (!isset($row['target_slug'])) {
                throw new RuntimeException('No available replacement slug was found for FAQ parent ' . $row['term_id'] . '.');
            }
        }
        return $rows;
    }

    /**
     * Require atomic storage before a statement modifies both terms and taxonomy.
     *
     * @return void
     * @throws RuntimeException If either term table is not an InnoDB table.
     */
    private function requireTransactionalTermTables(): void
    {
        global $wpdb;

        $tables = $this->queryRows($wpdb->prepare(
            'SHOW TABLE STATUS WHERE Name IN (%s, %s)', $wpdb->terms, $wpdb->term_taxonomy
        ));
        if (count($tables) !== 2) {
            throw new RuntimeException('The FAQ term table engines could not be verified.');
        }
        foreach ($tables as $table) {
            if (strtolower((string) ($table['Engine'] ?? '')) !== 'innodb') {
                throw new RuntimeException('Renaming conflicting FAQ parent slugs requires InnoDB term tables.');
            }
        }
    }

    /**
     * Move all validated parents in one statement, fenced by lease and snapshot.
     *
     * Conflicting legacy slugs change in that same atomic statement. Existing
     * target categories are never part of the update's destination selection.
     *
     * @param array<int, array<string, mixed>> $rows Expected legacy rows.
     * @param FaqCategoryRepairState $state Current lease owner.
     * @return void
     */
    private function moveAncestors(array $rows, FaqCategoryRepairState $state): void
    {
        global $wpdb;

        $conditions = [];
        $slugCases = [];
        $slugConflicts = [];
        $targetConflicts = [];
        foreach ($rows as $row) {
            $conditions[] = $wpdb->prepare(
                '(original.term_taxonomy_id = %d AND original.term_id = %d AND original.parent = %d
                  AND original.taxonomy = %s
                  AND CAST(original.description AS BINARY) = CAST(%s AS BINARY)
                  AND CAST(term.name AS BINARY) = CAST(%s AS BINARY)
                  AND CAST(term.slug AS BINARY) = CAST(%s AS BINARY))',
                $row['term_taxonomy_id'], $row['term_id'], $row['parent'], self::LEGACY,
                $row['description'], $row['name'], $row['slug']
            );
            if (isset($row['target_slug']) && $row['target_slug'] !== $row['slug']) {
                $slugCases[] = $wpdb->prepare('WHEN %d THEN %s', $row['term_id'], $row['target_slug']);
                $slugConflicts[] = $wpdb->prepare('(slug = %s AND term_id <> %d)', $row['target_slug'], $row['term_id']);
            }
            $targetConflicts[] = $wpdb->prepare(
                '(existing_term.slug = %s AND existing_term.term_id <> %d)',
                $row['target_slug'] ?? $row['slug'], $row['term_id']
            );
        }
        $ids = $this->idList($this->taxonomyIds($rows));
        // The aggregate materializes the snapshot, so a changed candidate makes
        // the whole UPDATE a no-op rather than leaving a partially moved set.
        $snapshot = implode(' OR ', $conditions);
        $targetGuard = $wpdb->prepare(
            "INNER JOIN (SELECT COUNT(*) AS conflicts FROM {$wpdb->terms} existing_term
             INNER JOIN {$wpdb->term_taxonomy} existing_taxonomy ON existing_taxonomy.term_id = existing_term.term_id
             WHERE existing_taxonomy.taxonomy = %s AND (" . implode(' OR ', $targetConflicts)
            . ')) AS target_guard ON target_guard.conflicts = 0', self::TARGET
        );
        $termJoin = '';
        $slugGuard = '';
        $slugAssignment = '';
        if ($slugCases !== []) {
            $termJoin = "INNER JOIN {$wpdb->terms} destination_term ON destination_term.term_id = destination.term_id";
            $slugGuard = "INNER JOIN (SELECT COUNT(*) AS conflicts FROM {$wpdb->terms} WHERE "
                . implode(' OR ', $slugConflicts) . ') AS slug_guard ON slug_guard.conflicts = 0';
            $slugAssignment = ', destination_term.slug = CASE destination.term_id '
                . implode(' ', $slugCases) . ' ELSE destination_term.slug END';
        }
        $this->execute($wpdb->prepare(
            "UPDATE {$wpdb->term_taxonomy} AS destination
             $termJoin
             INNER JOIN (
                 SELECT COUNT(*) AS matches FROM {$wpdb->term_taxonomy} original
                 INNER JOIN {$wpdb->terms} term ON term.term_id = original.term_id
                 WHERE $snapshot
             ) AS validated ON validated.matches = %d
             $slugGuard
             $targetGuard
             INNER JOIN {$wpdb->options} AS repair_lock ON " . $state->lockCondition() . "
             SET destination.taxonomy = %s $slugAssignment
             WHERE destination.taxonomy = %s AND destination.term_taxonomy_id IN ($ids)",
            count($rows), self::TARGET, self::LEGACY
        ));
        $state->assertOwned();
        // Verify actual state rather than affected rows: MYSQL_CLIENT_FOUND_ROWS
        // can count unchanged joined terms differently from the default client.
        if ($this->journalTaxonomy(['rows' => $rows]) !== self::TARGET) {
            throw new RuntimeException('The FAQ parent snapshot changed before it could be migrated.');
        }
    }

    /**
     * Complete repeatable cache/count work before recording successful migration.
     *
     * @param array<string, mixed> $details Durable pending snapshot.
     * @param FaqCategoryRepairState $state Current lease owner.
     * @return void
     */
    private function finish(array $details, FaqCategoryRepairState $state): void
    {
        global $wpdb;

        if ($this->journalTaxonomy($details) !== self::TARGET) {
            throw new RuntimeException('The FAQ parents have not all reached the target taxonomy.');
        }
        $this->assertTargetSlugs($details['rows']);
        $remaining = $this->inspect();
        if ($remaining['rows'] !== []) {
            throw new RuntimeException('The FAQ hierarchy changed during repair; additional legacy ancestors remain.');
        }
        $objects = array_values(array_unique(array_merge($details['object_ids'] ?? [], $this->linkedObjects($details['rows']))));
        $state->assertOwned();
        $this->recount($details['rows'], $state);

        $termIds = array_column($details['rows'], 'term_id');
        clean_term_cache($termIds, self::LEGACY, false);
        clean_term_cache($termIds, self::TARGET, false);
        foreach ([self::LEGACY, self::TARGET] as $taxonomy) {
            wp_cache_delete_multiple($objects, $taxonomy . '_relationships');
            // The old taxonomy need not be registered, so clean_taxonomy_cache
            // alone is insufficient to remove its persisted hierarchy.
            $this->execute($wpdb->prepare(
                "DELETE hierarchy FROM {$wpdb->options} hierarchy
                 INNER JOIN {$wpdb->options} repair_lock ON " . $state->lockCondition() . '
                 WHERE hierarchy.option_name = %s',
                $taxonomy . '_children'
            ));
            wp_cache_delete($taxonomy . '_children', 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            wp_cache_delete('all_ids', $taxonomy);
            wp_cache_delete('get', $taxonomy);
        }
        wp_cache_set_terms_last_changed();
        $state->assertOwned();

        // Rebuild from unfiltered rows: a language filter must not drop parents
        // from the persistent hierarchy shared by all languages on this site.
        $hierarchy = [];
        foreach ($this->selectRows($wpdb->prepare('tt.taxonomy = %s', self::TARGET)) as $row) {
            if ($row['parent'] > 0) {
                $hierarchy[$row['parent']][] = $row['term_id'];
            }
        }
        $serialized = maybe_serialize($hierarchy);
        $this->execute($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
             SELECT %s, %s, 'on' FROM {$wpdb->options} repair_lock WHERE " . $state->lockCondition() . '
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)',
            self::TARGET . '_children', $serialized
        ));
        wp_cache_delete(self::TARGET . '_children', 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        $stored = $this->queryRows($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::TARGET . '_children'
        ));
        if (count($stored) !== 1 || $stored[0]['option_value'] !== $serialized) {
            throw new RuntimeException('The repaired FAQ hierarchy cache could not be verified.');
        }
        $state->assertOwned();
        if ($this->journalTaxonomy($details) !== self::TARGET) {
            throw new RuntimeException('The FAQ parent taxonomy changed during final verification.');
        }
        $this->assertTargetSlugs($details['rows']);
        $state->clearDetails();
        $state->setStatus('done');
    }

    /**
     * Recount direct FAQ assignments with WordPress's configurable status list.
     *
     * Fencing the count UPDATE avoids an expired worker writing after takeover.
     * A separate comparison verifies persistence instead of trusting the core
     * recount API's unconditional true return value.
     *
     * @param array<int, array<string, mixed>> $rows Migrated ancestor rows.
     * @param FaqCategoryRepairState $state Current lease owner.
     * @return void
     */
    private function recount(array $rows, FaqCategoryRepairState $state): void
    {
        global $wpdb;

        $statuses = $this->countStatuses();
        $statusCondition = $statuses === [] ? '1 = 0' : $wpdb->prepare(
            'p.post_status IN (' . implode(', ', array_fill(0, count($statuses), '%s')) . ')', ...array_values($statuses)
        );
        $ids = $this->idList($this->taxonomyIds($rows));
        $counts = "SELECT tr.term_taxonomy_id, COUNT(*) AS total
                   FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                   WHERE tr.term_taxonomy_id IN ($ids) AND p.post_type = 'rrze_faq' AND $statusCondition
                   GROUP BY tr.term_taxonomy_id";
        $this->execute($wpdb->prepare(
            "UPDATE {$wpdb->term_taxonomy} tt
             LEFT JOIN ($counts) counted ON counted.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->options} repair_lock ON " . $state->lockCondition() . "
             SET tt.count = COALESCE(counted.total, 0)
             WHERE tt.taxonomy = %s AND tt.term_taxonomy_id IN ($ids) AND tt.count <> COALESCE(counted.total, 0)",
            self::TARGET
        ));
        $mismatch = $this->queryRows(
            "SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt
             LEFT JOIN ($counts) counted ON counted.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.term_taxonomy_id IN ($ids) AND tt.count <> COALESCE(counted.total, 0) LIMIT 1"
        );
        $state->assertOwned();
        if ($mismatch !== []) {
            throw new RuntimeException('The repaired FAQ category counts could not be verified.');
        }
    }

    /**
     * Validate the count policy before mutation and when resuming finalization.
     *
     * @return string[] Post statuses counted by WordPress for the FAQ taxonomy.
     * @throws RuntimeException If another integration changed the count strategy.
     */
    private function countStatuses(): array
    {
        $taxonomy = get_taxonomy(self::TARGET);
        if (!$taxonomy || $taxonomy->object_type !== ['rrze_faq'] || !empty($taxonomy->update_count_callback)) {
            throw new RuntimeException('The FAQ taxonomy count configuration requires manual inspection.');
        }
        $statuses = apply_filters('update_post_term_count_statuses', ['publish'], $taxonomy);
        if (!is_array($statuses) || array_filter($statuses, 'is_string') !== $statuses) {
            throw new RuntimeException('The FAQ term count statuses are invalid.');
        }
        return array_values($statuses);
    }

    /**
     * Verify final slug uniqueness before and after repeatable cache/count work.
     *
     * Replacement slugs remain globally unique; unchanged slugs only need to be
     * unique within the FAQ taxonomy, preserving unrelated historical terms.
     *
     * @param array<int, array<string, mixed>> $rows Migrated row snapshots.
     * @return void
     * @throws RuntimeException If another term now occupies a migrated slug.
     */
    private function assertTargetSlugs(array $rows): void
    {
        global $wpdb;

        $conditions = [];
        foreach ($rows as $row) {
            $condition = $wpdb->prepare(
                '(t.slug = %s AND t.term_id <> %d', $row['target_slug'] ?? $row['slug'], $row['term_id']
            );
            if (!isset($row['target_slug'])) {
                $condition .= $wpdb->prepare(' AND tt.taxonomy = %s', self::TARGET);
            }
            $conditions[] = $condition . ')';
        }
        $conflicts = $this->queryRows(
            "SELECT t.term_id FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             WHERE " . implode(' OR ', $conditions) . ' LIMIT 1'
        );
        if ($conflicts !== []) {
            throw new RuntimeException('A migrated FAQ parent slug is now occupied by another term.');
        }
    }

    /**
     * Validate durable row identities and determine whether SQL already committed.
     *
     * @param array<string, mixed> $details Durable pending snapshot.
     * @return string The single current taxonomy of every recorded parent.
     */
    private function journalTaxonomy(array $details): string
    {
        $expected = $details['rows'] ?? null;
        if (!is_array($expected) || $expected === []) {
            throw new RuntimeException('The pending FAQ category journal is invalid.');
        }
        $ids = $this->taxonomyIds($expected);
        $actual = $this->selectRows('tt.term_taxonomy_id IN (' . $this->idList($ids) . ')');
        if (count($actual) !== count($expected)) {
            throw new RuntimeException('A pending FAQ parent row is missing.');
        }
        $byId = array_column($actual, null, 'term_taxonomy_id');
        $taxonomies = [];
        foreach ($expected as $row) {
            if (array_key_exists('target_slug', $row)
                && (!is_string($row['target_slug']) || $row['target_slug'] === '' || strlen($row['target_slug']) > 200)) {
                throw new RuntimeException('The pending FAQ parent target slug is invalid.');
            }
            $found = $byId[$row['term_taxonomy_id']] ?? [];
            foreach (['term_taxonomy_id', 'term_id', 'parent', 'name', 'slug', 'description'] as $field) {
                $value = $row[$field] ?? null;
                if ($field === 'slug' && ($found['taxonomy'] ?? '') === self::TARGET) {
                    $value = $row['target_slug'] ?? $value;
                }
                if (!array_key_exists($field, $row) || !array_key_exists($field, $found) || $found[$field] !== $value) {
                    throw new RuntimeException('Pending FAQ parent ' . ($row['term_id'] ?? '?') . ' changed unexpectedly.');
                }
            }
            $taxonomies[$found['taxonomy']] = true;
        }
        if (count($taxonomies) !== 1 || !in_array(key($taxonomies), [self::LEGACY, self::TARGET], true)) {
            throw new RuntimeException('The pending FAQ parents have inconsistent taxonomy assignments.');
        }
        return (string) key($taxonomies);
    }

    /**
     * Validate direct relationships and collect object IDs for both cache groups.
     *
     * @param array<int, array<string, mixed>> $rows Candidate/migrated rows.
     * @return int[] Distinct existing FAQ post IDs, regardless of post status.
     */
    private function linkedObjects(array $rows): array
    {
        global $wpdb;

        $ids = $this->idList($this->taxonomyIds($rows));
        $relationships = $this->queryRows(
            "SELECT tr.object_id, tr.term_taxonomy_id, p.post_type
             FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id IN ($ids)"
        );
        $objects = [];
        foreach ($relationships as $relationship) {
            if ($relationship['post_type'] !== 'rrze_faq') {
                throw new RuntimeException('FAQ parent taxonomy row ' . $relationship['term_taxonomy_id']
                    . ' references missing or non-FAQ object ' . $relationship['object_id'] . '.');
            }
            $objects[] = (int) $relationship['object_id'];
        }
        return array_values(array_unique($objects));
    }

    /**
     * Read stable row snapshots without requiring the legacy taxonomy registration.
     *
     * @param string $where Already prepared SQL predicate on the tt alias.
     * @return array<int, array<string, mixed>> Rows with normalized integer fields.
     */
    private function selectRows(string $where): array
    {
        global $wpdb;

        $rows = $this->queryRows(
            "SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy, tt.parent, tt.count,
                    tt.description, t.name, t.slug
             FROM {$wpdb->term_taxonomy} tt LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE $where ORDER BY tt.term_taxonomy_id"
        );
        foreach ($rows as &$row) {
            foreach (['term_taxonomy_id', 'term_id', 'parent', 'count'] as $field) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $rows;
    }

    /**
     * Return sorted, unique taxonomy IDs from a nonempty, valid row collection.
     *
     * @param array<int, array<string, mixed>> $rows Row snapshots.
     * @return int[] Taxonomy IDs in deterministic order.
     */
    private function taxonomyIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['term_taxonomy_id']) || !is_int($row['term_taxonomy_id']) || $row['term_taxonomy_id'] < 1) {
                throw new RuntimeException('The FAQ repair contains an invalid taxonomy ID.');
            }
            $ids[] = $row['term_taxonomy_id'];
        }
        sort($ids, SORT_NUMERIC);
        if (count(array_unique($ids)) !== count($ids)) {
            throw new RuntimeException('The FAQ repair contains duplicate taxonomy IDs.');
        }
        return $ids;
    }

    /**
     * Format validated integer IDs for internal SQL IN clauses.
     *
     * @param int[] $ids Nonempty positive IDs.
     * @return string Comma-separated decimal IDs only.
     */
    private function idList(array $ids): string
    {
        if ($ids === [] || array_filter($ids,
            /**
             * Detect values unsuitable for an integer-only SQL selection.
             *
             * @param mixed $id Candidate ID.
             * @return bool Whether it is invalid.
             */
            static fn ($id): bool => !is_int($id) || $id < 1
        ) !== []) {
            throw new RuntimeException('The FAQ repair contains an invalid ID selection.');
        }
        return implode(',', $ids);
    }

    /**
     * Execute a prepared read without leaking database diagnostics into responses.
     *
     * @param string $sql Prepared SQL statement.
     * @return array<int, array<string, mixed>> Database result rows.
     */
    private function queryRows(string $sql): array
    {
        global $wpdb;

        $previous = $wpdb->suppress_errors(true);
        try {
            $result = $wpdb->get_results($sql, ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($result)) {
                throw new RuntimeException('A database read for the FAQ category repair failed.');
            }
            return $result;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    /**
     * Execute a prepared write, distinguishing zero affected rows from SQL failure.
     *
     * @param string $sql Prepared SQL statement.
     * @return int Number of affected rows.
     */
    private function execute(string $sql): int
    {
        global $wpdb;

        $previous = $wpdb->suppress_errors(true);
        try {
            $result = $wpdb->query($sql);
            if ($result === false || $wpdb->last_error !== '') {
                throw new RuntimeException('A database write for the FAQ category repair failed.');
            }
            return (int) $result;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }
}
