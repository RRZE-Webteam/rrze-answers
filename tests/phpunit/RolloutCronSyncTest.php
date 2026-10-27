<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\API\SyncAPI;
use WP_Error;
use WP_UnitTestCase;

/**
 * Exercise the synchronization write boundary with WP-Cron's anonymous user.
 *
 * HTTP stays local through pre_http_request. These assertions deliberately use
 * the same public SyncAPI contract on main and on the rollout candidate so a
 * failure can be classified separately from a newly introduced regression.
 */
final class RolloutCronSyncTest extends WP_UnitTestCase
{
    private const SOURCE = 'rollout-source';
    private const SOURCE_URL = 'https://rollout-source.example.org';

    /**
     * Require category and tag relationships after an anonymous sync write.
     *
     * Calling the write boundary directly avoids cron logging and scheduling
     * side effects while retaining the relevant current-user capability state.
     *
     * @dataProvider synchronizationCases
     * @param string $type Answers content type without its rrze_ prefix.
     * @param bool $update Whether an existing import must be updated in place.
     */
    public function testAnonymousSyncAssignsNewAndChangedTerms(string $type, bool $update): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $postId = null;
        $categoryTaxonomy = 'rrze_' . $type . '_category';
        $tagTaxonomy = 'rrze_' . $type . '_tag';

        if ($update) {
            $postId = self::factory()->post->create([
                'post_type' => 'rrze_' . $type,
                'post_status' => 'publish',
                'post_title' => 'Previous imported entry',
                'meta_input' => [
                    'source' => self::SOURCE,
                    'remoteID' => 812,
                    'remoteChanged' => 100,
                    'lang' => 'de',
                ],
            ]);
            self::assertNotWPError(wp_set_object_terms($postId, ['Previous category'], $categoryTaxonomy));
            self::assertNotWPError(wp_set_object_terms($postId, ['Previous tag'], $tagTaxonomy));
        }

        $entry = [
            'id' => 812,
            'source' => 'website',
            'remoteID' => 812,
            'remoteChanged' => 200,
            'title' => ['rendered' => 'Current imported entry'],
            'content' => ['rendered' => '<p>Current imported content</p>'],
            'lang' => 'de',
            'link' => self::SOURCE_URL . '/' . $type . '/812',
            $categoryTaxonomy => ['Current category'],
            $tagTaxonomy => ['Current tag'],
        ];
        /**
         * Return the complete offline snapshot for either consumer generation.
         *
         * @param mixed $preempt Existing short-circuit response.
         * @param array<string, mixed> $arguments HTTP request options.
         * @param string $url Requested source URL.
         * @return array<string, mixed>|WP_Error
         */
        $response = static function ($preempt, array $arguments, string $url) use ($entry, $type) {
            if (!str_starts_with($url, self::SOURCE_URL . '/wp-json/wp/v2/' . $type . '?')) {
                return new WP_Error('unexpected_rollout_http', 'Rollout checks may only use their offline source.');
            }
            parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

            return [
                'headers' => ['x-wp-totalpages' => '1'],
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode((int) ($query['page'] ?? 1) === 1 ? [$entry] : []),
                'cookies' => [],
            ];
        };
        add_filter('pre_http_request', $response, 10, 3);

        try {
            wp_set_current_user(0);
            self::assertFalse(current_user_can(get_taxonomy($categoryTaxonomy)->cap->assign_terms));
            $result = (new SyncAPI())->setEntries($type, self::SOURCE, 'all', self::SOURCE_URL);

            self::assertNotWPError($result);
            self::assertSame($update ? 1 : 0, $result['iUpdated']);
            self::assertSame($update ? 0 : 1, $result['iNew']);

            $posts = get_posts([
                'post_type' => 'rrze_' . $type,
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_key' => 'source',
                'meta_value' => self::SOURCE,
            ]);
            self::assertCount(1, $posts);
            if ($update) {
                self::assertSame($postId, $posts[0]->ID);
            }
            self::assertSame('Current imported entry', $posts[0]->post_title);
            self::assertSame('200', (string) get_post_meta($posts[0]->ID, 'remoteChanged', true));
            self::assertSame(
                ['categories' => ['Current category'], 'tags' => ['Current tag']],
                [
                    'categories' => wp_get_post_terms($posts[0]->ID, $categoryTaxonomy, ['fields' => 'names']),
                    'tags' => wp_get_post_terms($posts[0]->ID, $tagTaxonomy, ['fields' => 'names']),
                ],
                'A successful anonymous sync must persist category and tag relationships, not just post content.'
            );
        } finally {
            remove_filter('pre_http_request', $response, 10);
        }
    }

    /**
     * Cover both synchronized post types and both write paths.
     *
     * @return array<string, array{string, bool}>
     */
    public static function synchronizationCases(): array
    {
        return [
            'FAQ insert' => ['faq', false],
            'FAQ update' => ['faq', true],
            'Glossary insert' => ['glossary', false],
            'Glossary update' => ['glossary', true],
        ];
    }
}
