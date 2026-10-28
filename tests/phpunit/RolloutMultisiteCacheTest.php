<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Tools;
use WP_UnitTestCase;

/** Detect request-local synchronization state leaking between multisite blogs. */
final class RolloutMultisiteCacheTest extends WP_UnitTestCase
{
    /**
     * Require hasSync() to follow the currently selected blog in both directions.
     *
     * A dedicated post type gives this test a fresh static-cache key even when
     * an earlier test already inspected FAQ or glossary administrative columns.
     *
     * @dataProvider initialStates
     * @param bool $firstHasSync Whether the original blog contains an import.
     */
    public function testHasSyncDoesNotReuseAnotherBlogsResult(bool $firstHasSync): void
    {
        self::assertTrue(is_multisite());
        $postType = $firstHasSync ? 'rrze_rollout_yes' : 'rrze_rollout_no';
        register_post_type($postType, ['public' => true]);
        $otherSite = self::factory()->blog->create();
        self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'meta_input' => ['source' => $firstHasSync ? 'remote' : 'website'],
        ]);
        $tools = new Tools();
        self::assertSame($firstHasSync, $tools->hasSync($postType));

        switch_to_blog($otherSite);
        try {
            self::factory()->post->create([
                'post_type' => $postType,
                'post_status' => 'publish',
                'meta_input' => ['source' => $firstHasSync ? 'website' : 'remote'],
            ]);
            self::assertSame(
                !$firstHasSync,
                $tools->hasSync($postType),
                'Synchronization availability must be computed for the current blog after switch_to_blog().'
            );
        } finally {
            restore_current_blog();
            unregister_post_type($postType);
        }
        self::assertSame($firstHasSync, $tools->hasSync($postType));
    }

    /** Verify metadata edits and post lifecycle changes invalidate cached state. */
    public function testHasSyncTracksChangesWithinTheSameRequest(): void
    {
        $postType = 'rrze_rollout_changes';
        register_post_type($postType, ['public' => true]);
        $tools = new Tools();

        try {
            self::assertFalse($tools->hasSync($postType));
            $postId = self::factory()->post->create([
                'post_type' => $postType,
                'post_status' => 'publish',
                'meta_input' => ['source' => 'website'],
            ]);
            self::assertFalse($tools->hasSync($postType));
            update_post_meta($postId, 'source', 'remote');
            self::assertTrue($tools->hasSync($postType));
            update_post_meta($postId, 'source', 'website');
            self::assertFalse($tools->hasSync($postType));
            update_post_meta($postId, 'source', 'remote');
            self::assertTrue($tools->hasSync($postType));
            wp_trash_post($postId);
            self::assertFalse($tools->hasSync($postType));
            wp_update_post(['ID' => $postId, 'post_status' => 'publish']);
            self::assertTrue($tools->hasSync($postType));
            delete_post_meta($postId, 'source');
            self::assertFalse($tools->hasSync($postType));
            add_post_meta($postId, 'source', 'remote');
            self::assertTrue($tools->hasSync($postType));
            wp_delete_post($postId, true);
            self::assertFalse($tools->hasSync($postType));
        } finally {
            unregister_post_type($postType);
        }
    }

    /**
     * Cover both a stale positive and a stale negative cache entry.
     *
     * @return array<string, array{bool}>
     */
    public static function initialStates(): array
    {
        return [
            'imported then local' => [true],
            'local then imported' => [false],
        ];
    }
}
