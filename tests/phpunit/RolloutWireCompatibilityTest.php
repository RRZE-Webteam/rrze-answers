<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\API\SyncAPI;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Connect real REST producer responses to a consumer without external HTTP.
 *
 * By default both sides use the loaded plugin. Separate main/candidate PHP
 * processes may exchange these synthetic fixtures using the capture/consume
 * directory environment variables, without mixing classes from two versions.
 */
final class RolloutWireCompatibilityTest extends WP_UnitTestCase
{
    private const SOURCE_URL = 'https://rollout-wire.example.org';
    private const SOURCE = 'rollout-wire';

    /**
     * Preserve taxonomy names across site-local IDs and producer generations.
     *
     * @dataProvider synchronizedTypes
     * @param string $type Answers content type without its rrze_ prefix.
     */
    public function testConsumesActualProducerResponse(string $type): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $fixture = $this->captureProducer($type);
        $captureDirectory = getenv('RRZE_ROLLOUT_WIRE_CAPTURE_DIR');
        if (is_string($captureDirectory) && $captureDirectory !== '') {
            self::assertDirectoryExists($captureDirectory);
            self::assertNotFalse(file_put_contents(
                $captureDirectory . '/' . $type . '.json',
                json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            ));
        }

        $consumeDirectory = getenv('RRZE_ROLLOUT_WIRE_CONSUME_DIR');
        if (is_string($consumeDirectory) && $consumeDirectory !== '') {
            $fixturePath = $consumeDirectory . '/' . $type . '.json';
            self::assertFileExists($fixturePath);
            $fixture = json_decode(file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
        }

        $categoryTaxonomy = 'rrze_' . $type . '_category';
        $tagTaxonomy = 'rrze_' . $type . '_tag';
        for ($offset = 0; $offset < 8; $offset++) {
            self::assertNotWPError(wp_insert_term('Destination distractor category ' . $offset, $categoryTaxonomy));
            self::assertNotWPError(wp_insert_term('Destination distractor tag ' . $offset, $tagTaxonomy));
        }
        $localCategory = wp_insert_term('Wire source category', $categoryTaxonomy);
        $localTag = wp_insert_term('Wire source tag', $tagTaxonomy);
        self::assertNotWPError($localCategory);
        self::assertNotWPError($localTag);
        self::assertNotContains((int) $localCategory['term_id'], $fixture['entry'][$categoryTaxonomy]);
        self::assertNotContains((int) $localTag['term_id'], $fixture['entry'][$tagTaxonomy]);

        /**
         * Replay the exact source controller data requested by this consumer.
         *
         * @param mixed $preempt Existing short-circuit response.
         * @param array<string, mixed> $arguments HTTP options.
         * @param string $url Requested source URL.
         * @return array<string, mixed>|WP_Error
         */
        $response = static function ($preempt, array $arguments, string $url) use (&$fixture, $type) {
            if (!str_starts_with($url, self::SOURCE_URL . '/wp-json/wp/v2/')) {
                return new WP_Error('unexpected_rollout_http', 'Wire tests must use their captured local producer.');
            }
            parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
            $route = basename((string) wp_parse_url($url, PHP_URL_PATH));
            if ($route === $type) {
                $entry = isset($query['_embed']) ? $fixture['embedded_entry'] : $fixture['entry'];
                $body = (int) ($query['page'] ?? 1) === 1 ? [$entry] : [];
            } elseif ($route === 'rrze_' . $type . '_category') {
                $body = $fixture['category_terms'];
            } elseif ($route === 'rrze_' . $type . '_tag') {
                $body = $fixture['tag_terms'];
            } else {
                return new WP_Error('unexpected_rollout_route', 'The consumer requested an uncaptured REST route.');
            }

            return [
                'headers' => ['x-wp-totalpages' => '1'],
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode($body),
                'cookies' => [],
            ];
        };
        add_filter('pre_http_request', $response, 10, 3);

        try {
            $result = (new SyncAPI())->setEntries($type, self::SOURCE, 'wire-source-category', self::SOURCE_URL);
            self::assertNotWPError($result);
            self::assertSame(1, $result['iNew']);
            $posts = $this->getImportedPosts($type);
            self::assertCount(1, $posts);
            self::assertSame($fixture['entry']['title']['rendered'], $posts[0]->post_title);
            self::assertSame(
                ['categories' => ['Wire source category'], 'tags' => ['Wire source tag']],
                [
                    'categories' => wp_get_post_terms($posts[0]->ID, 'rrze_' . $type . '_category', ['fields' => 'names']),
                    'tags' => wp_get_post_terms($posts[0]->ID, 'rrze_' . $type . '_tag', ['fields' => 'names']),
                ],
                'A consumer must resolve taxonomy IDs on the source site, never against its local terms.'
            );
            self::assertSame([(int) $localCategory['term_id']], wp_get_post_terms($posts[0]->ID, $categoryTaxonomy, ['fields' => 'ids']));
            self::assertSame([(int) $localTag['term_id']], wp_get_post_terms($posts[0]->ID, $tagTaxonomy, ['fields' => 'ids']));

            $repeat = (new SyncAPI())->setEntries($type, self::SOURCE, 'wire-source-category', self::SOURCE_URL);
            self::assertNotWPError($repeat);
            self::assertSame(0, $repeat['iNew']);
            self::assertSame(0, $repeat['iUpdated']);
            self::assertCount(1, $this->getImportedPosts($type));

            $fixture = $fixture['updated'];
            $changed = (new SyncAPI())->setEntries($type, self::SOURCE, 'wire-source-category', self::SOURCE_URL);
            self::assertNotWPError($changed);
            self::assertSame(0, $changed['iNew']);
            self::assertSame(1, $changed['iUpdated']);
            $updatedPosts = $this->getImportedPosts($type);
            self::assertCount(1, $updatedPosts);
            self::assertSame($posts[0]->ID, $updatedPosts[0]->ID);
            self::assertSame(
                ['categories' => ['Changed wire category'], 'tags' => ['Changed wire tag']],
                [
                    'categories' => wp_get_post_terms($posts[0]->ID, $categoryTaxonomy, ['fields' => 'names']),
                    'tags' => wp_get_post_terms($posts[0]->ID, $tagTaxonomy, ['fields' => 'names']),
                ],
                'Changed source taxonomy names with a newer remoteChanged must replace the previous relationships.'
            );
        } finally {
            remove_filter('pre_http_request', $response, 10);
        }
    }

    /**
     * Capture unembedded, embedded and taxonomy responses from another blog.
     *
     * @param string $type Answers content type to seed on the source blog.
     * @return array<string, mixed> Synthetic responses from the loaded producer.
     */
    private function captureProducer(string $type): array
    {
        $sourceSite = self::factory()->blog->create();
        switch_to_blog($sourceSite);

        try {
            $categoryTaxonomy = 'rrze_' . $type . '_category';
            $tagTaxonomy = 'rrze_' . $type . '_tag';
            $category = wp_insert_term('Wire source category', $categoryTaxonomy);
            $tag = wp_insert_term('Wire source tag', $tagTaxonomy);
            self::assertNotWPError($category);
            self::assertNotWPError($tag);
            $postId = self::factory()->post->create([
                'post_type' => 'rrze_' . $type,
                'post_status' => 'publish',
                'post_title' => 'Wire exported ' . $type,
                'post_content' => '<p>Wire exported content</p>',
                'meta_input' => [
                    'source' => 'website',
                    'remoteID' => 41812,
                    'remoteChanged' => 100,
                    'lang' => 'de',
                ],
            ]);
            self::assertNotWPError(wp_set_object_terms($postId, [(int) $category['term_id']], $categoryTaxonomy));
            self::assertNotWPError(wp_set_object_terms($postId, [(int) $tag['term_id']], $tagTaxonomy));
            $snapshot = $this->captureEntrySnapshot($type, $postId, (int) $category['term_id'], (int) $tag['term_id']);
            self::assertNotWPError(wp_update_term((int) $category['term_id'], $categoryTaxonomy, ['name' => 'Changed wire category']));
            self::assertNotWPError(wp_update_term((int) $tag['term_id'], $tagTaxonomy, ['name' => 'Changed wire tag']));
            update_post_meta($postId, 'remoteChanged', 200);
            $snapshot['updated'] = $this->captureEntrySnapshot($type, $postId, (int) $category['term_id'], (int) $tag['term_id']);

            return $snapshot;
        } finally {
            restore_current_blog();
        }
    }

    /**
     * Serialize one actual source state, including both REST representations.
     *
     * @param string $type Answers content type.
     * @param int $postId Source post ID.
     * @param int $categoryId Source category ID.
     * @param int $tagId Source tag ID.
     * @return array<string, mixed> Controller responses for a complete snapshot.
     */
    private function captureEntrySnapshot(string $type, int $postId, int $categoryId, int $tagId): array
    {
        $server = rest_get_server();
        $request = new WP_REST_Request('GET', '/wp/v2/' . $type);
        $request->set_param('include', [$postId]);
        $response = $server->dispatch($request);
        self::assertSame(200, $response->get_status());
        $plain = $server->response_to_data($response, false);
        $embedded = $server->response_to_data($response, ['wp:term']);

        return [
            'entry' => $plain[0],
            'embedded_entry' => $embedded[0],
            'category_terms' => $this->captureTerm('rrze_' . $type . '_category', $categoryId),
            'tag_terms' => $this->captureTerm('rrze_' . $type . '_tag', $tagId),
        ];
    }

    /**
     * Find this probe's destination posts without including its producer blog.
     *
     * @param string $type Answers content type.
     * @return \WP_Post[] Imported posts belonging to this fixture source.
     */
    private function getImportedPosts(string $type): array
    {
        return get_posts([
            'post_type' => 'rrze_' . $type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_key' => 'source',
            'meta_value' => self::SOURCE,
        ]);
    }

    /**
     * Read one source term through its registered REST collection controller.
     *
     * @param string $taxonomy Registered taxonomy and REST base.
     * @param int $termId Source-site term ID.
     * @return array<int, array<string, mixed>> Serialized term collection.
     */
    private function captureTerm(string $taxonomy, int $termId): array
    {
        $request = new WP_REST_Request('GET', '/wp/v2/' . $taxonomy);
        $request->set_param('include', [$termId]);
        $response = rest_get_server()->dispatch($request);
        self::assertSame(200, $response->get_status());

        return $response->get_data();
    }

    /**
     * Exercise each content type participating in cross-site synchronization.
     *
     * @return array<string, array{string}>
     */
    public static function synchronizedTypes(): array
    {
        return ['FAQ' => ['faq'], 'Glossary' => ['glossary']];
    }
}
