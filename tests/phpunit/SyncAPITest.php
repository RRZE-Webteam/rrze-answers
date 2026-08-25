<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\API\SyncAPI;
use WP_Error;
use WP_UnitTestCase;

final class SyncAPITest extends WP_UnitTestCase
{
    private const SOURCE_IDENTIFIER = 'source-a';
    private const SOURCE_URL = 'https://source.example.org';

    private SyncAPI $syncApi;

    public function set_up(): void
    {
        parent::set_up();

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->syncApi = new SyncAPI();
    }

    public function testTransportFailurePreservesExistingEntries(): void
    {
        $existingPostId = $this->createSynchronizedPost('faq', 10, 100);

        $this->mockHttpRequests(
            static fn (): WP_Error => new WP_Error('network_unavailable', 'Network unavailable')
        );

        $result = $this->synchronize('faq');

        self::assertWPError($result);
        self::assertSame('network_unavailable', $result->get_error_code());
        self::assertSame('publish', get_post_status($existingPostId));
    }

    public function testInvalidJsonPreservesExistingEntries(): void
    {
        $existingPostId = $this->createSynchronizedPost('faq', 10, 100);

        $this->mockHttpPages([
            1 => $this->httpResponse('{not-json', 200, 1),
        ]);

        $result = $this->synchronize('faq');

        self::assertWPError($result);
        self::assertSame('remote_invalid_response', $result->get_error_code());
        self::assertSame('publish', get_post_status($existingPostId));
    }

    public function testEmptyResponsePreservesExistingEntries(): void
    {
        $existingPostId = $this->createSynchronizedPost('faq', 10, 100);

        $this->mockHttpPages([
            1 => $this->httpResponse([], 200, 0),
        ]);

        $result = $this->synchronize('faq');

        self::assertWPError($result);
        self::assertSame('remote_empty_response', $result->get_error_code());
        self::assertSame('publish', get_post_status($existingPostId));
    }

    public function testEmptyResponseIsAllowedWhenNothingWasPreviouslyImported(): void
    {
        $this->mockHttpPages([
            1 => $this->httpResponse([], 200, 0),
        ]);

        $result = $this->synchronize('faq');

        self::assertIsArray($result);
        self::assertSame(0, $result['iNew']);
        self::assertSame(0, $result['iUpdated']);
        self::assertSame(0, $result['iDeleted']);
    }

    public function testIncompletePaginationDoesNotApplyPartiallyFetchedData(): void
    {
        $existingPostId = $this->createSynchronizedPost(
            'faq',
            10,
            100,
            self::SOURCE_IDENTIFIER,
            'Original title'
        );

        $this->mockHttpPages([
            1 => $this->httpResponse(
                [$this->remoteEntry('faq', 10, 200, 'Changed title')],
                200,
                2
            ),
            2 => $this->httpResponse([], 200, 2),
        ]);

        $result = $this->synchronize('faq');

        self::assertWPError($result);
        self::assertSame('remote_incomplete_response', $result->get_error_code());
        self::assertSame('Original title', get_the_title($existingPostId));
        self::assertSame('100', (string) get_post_meta($existingPostId, 'remoteChanged', true));
    }

    public function testDuplicateRemoteIdDoesNotModifyExistingContent(): void
    {
        $existingPostId = $this->createSynchronizedPost(
            'faq',
            10,
            100,
            self::SOURCE_IDENTIFIER,
            'Original title'
        );
        $duplicateEntry = $this->remoteEntry('faq', 10, 200, 'Changed title');

        $this->mockHttpPages([
            1 => $this->httpResponse([$duplicateEntry, $duplicateEntry], 200, 1),
        ]);

        $result = $this->synchronize('faq');

        self::assertWPError($result);
        self::assertSame('remote_duplicate_entry', $result->get_error_code());
        self::assertSame('Original title', get_the_title($existingPostId));
    }

    public function testPostWriteFailurePreventsObsoleteEntryDeletion(): void
    {
        $obsoletePost = $this->createSynchronizedPost('faq', 20, 100);

        add_filter(
            'wp_insert_post_empty_content',
            static function (bool $isEmpty, array $postData): bool {
                return ($postData['post_title'] ?? '') === 'Rejected entry' ? true : $isEmpty;
            },
            10,
            2
        );

        $this->mockHttpPages([
            1 => $this->httpResponse(
                [$this->remoteEntry('faq', 50, 100, 'Rejected entry')],
                200,
                1
            ),
        ]);

        $result = $this->synchronize('faq');

        self::assertWPError($result);
        self::assertSame('empty_content', $result->get_error_code());
        self::assertSame('publish', get_post_status($obsoletePost));
    }

    /** @dataProvider taxonomyWriteFailures */
    public function testTaxonomyWriteFailurePreventsObsoleteEntryDeletion(
        string $failingTaxonomy
    ): void {
        $obsoletePost = $this->createSynchronizedPost('faq', 20, 100);

        add_filter(
            'pre_insert_term',
            static function ($term, string $taxonomy) use ($failingTaxonomy) {
                if ($taxonomy === $failingTaxonomy) {
                    return new WP_Error('term_write_failed', 'Term write failed');
                }

                return $term;
            },
            10,
            2
        );

        $this->mockHttpPages([
            1 => $this->httpResponse(
                [$this->remoteEntry('faq', 50, 100, 'New entry')],
                200,
                1
            ),
        ]);

        $result = $this->synchronize('faq');

        self::assertWPError($result);
        self::assertSame('term_write_failed', $result->get_error_code());
        self::assertSame('publish', get_post_status($obsoletePost));
    }

    /** @dataProvider synchronizedPostTypes */
    public function testSuccessfulSyncReconcilesOnlyTheRequestedSource(string $type): void
    {
        $postToUpdate = $this->createSynchronizedPost(
            $type,
            10,
            100,
            self::SOURCE_IDENTIFIER,
            'Original title'
        );
        $obsoletePost = $this->createSynchronizedPost($type, 20, 100);
        $otherSourcePost = $this->createSynchronizedPost($type, 30, 100, 'source-b');
        $localPost = $this->createSynchronizedPost($type, 40, 100, 'website');

        $this->mockHttpPages([
            1 => $this->httpResponse(
                [
                    $this->remoteEntry($type, 10, 200, 'Updated title'),
                    $this->remoteEntry($type, 50, 100, 'New title'),
                ],
                200,
                1
            ),
        ]);

        $result = $this->synchronize($type);

        self::assertIsArray($result);
        self::assertSame(1, $result['iNew']);
        self::assertSame(1, $result['iUpdated']);
        self::assertSame(1, $result['iDeleted']);
        self::assertSame([], $result['URLhasSlider']);

        self::assertSame('Updated title', get_the_title($postToUpdate));
        self::assertSame('200', (string) get_post_meta($postToUpdate, 'remoteChanged', true));
        self::assertStringContainsString(
            self::SOURCE_URL . '/relative-link',
            (string) get_post_field('post_content', $postToUpdate)
        );

        self::assertFalse(get_post_status($obsoletePost));
        self::assertSame('publish', get_post_status($otherSourcePost));
        self::assertSame('publish', get_post_status($localPost));

        $newPost = $this->findSynchronizedPost($type, 50);
        self::assertNotNull($newPost);
        self::assertSame('New title', get_the_title($newPost));
        self::assertSame(self::SOURCE_IDENTIFIER, get_post_meta($newPost, 'source', true));

        $categoryNames = wp_get_post_terms(
            $newPost,
            'rrze_' . $type . '_category',
            ['fields' => 'names']
        );
        $tagNames = wp_get_post_terms(
            $newPost,
            'rrze_' . $type . '_tag',
            ['fields' => 'names']
        );

        self::assertSame(['Imported category'], $categoryNames);
        self::assertSame(['Imported tag'], $tagNames);
    }

    public function testSuccessfulPaginationIsCollectedBeforeReconciliation(): void
    {
        $this->mockHttpPages([
            1 => $this->httpResponse(
                [$this->remoteEntry('faq', 10, 100, 'Page one')],
                200,
                2
            ),
            2 => $this->httpResponse(
                [$this->remoteEntry('faq', 20, 100, 'Page two')],
                200,
                2
            ),
        ]);

        $result = $this->synchronize('faq');

        self::assertIsArray($result);
        self::assertSame(2, $result['iNew']);
        self::assertNotNull($this->findSynchronizedPost('faq', 10));
        self::assertNotNull($this->findSynchronizedPost('faq', 20));
    }

    public function testSyncOnlyTouchesTheCurrentMultisiteSite(): void
    {
        $otherSiteId = self::factory()->blog->create();

        switch_to_blog($otherSiteId);
        $otherSitePost = $this->createSynchronizedPost('faq', 99, 100);
        restore_current_blog();

        $this->mockHttpPages([
            1 => $this->httpResponse(
                [$this->remoteEntry('faq', 10, 100, 'Current site entry')],
                200,
                1
            ),
        ]);

        $result = $this->synchronize('faq');

        self::assertIsArray($result);
        self::assertNotNull($this->findSynchronizedPost('faq', 10));

        switch_to_blog($otherSiteId);
        self::assertSame('publish', get_post_status($otherSitePost));
        self::assertSame(self::SOURCE_IDENTIFIER, get_post_meta($otherSitePost, 'source', true));
        restore_current_blog();
    }

    /** @return array<string, array{string}> */
    public static function synchronizedPostTypes(): array
    {
        return [
            'FAQ' => ['faq'],
            'Glossary' => ['glossary'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function taxonomyWriteFailures(): array
    {
        return [
            'FAQ category' => ['rrze_faq_category'],
            'FAQ tag' => ['rrze_faq_tag'],
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function synchronize(string $type)
    {
        return $this->syncApi->setEntries(
            $type,
            self::SOURCE_IDENTIFIER,
            'all',
            self::SOURCE_URL
        );
    }

    private function createSynchronizedPost(
        string $type,
        int $remoteId,
        int $remoteChanged,
        string $source = self::SOURCE_IDENTIFIER,
        string $title = 'Existing synchronized post'
    ): int {
        return self::factory()->post->create(
            [
                'post_type' => 'rrze_' . $type,
                'post_status' => 'publish',
                'post_title' => $title,
                'meta_input' => [
                    'source' => $source,
                    'remoteID' => $remoteId,
                    'remoteChanged' => $remoteChanged,
                    'lang' => 'de',
                ],
            ]
        );
    }

    private function findSynchronizedPost(string $type, int $remoteId): ?int
    {
        $postIds = get_posts(
            [
                'post_type' => 'rrze_' . $type,
                'post_status' => 'any',
                'numberposts' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'source',
                        'value' => self::SOURCE_IDENTIFIER,
                    ],
                    [
                        'key' => 'remoteID',
                        'value' => $remoteId,
                    ],
                ],
            ]
        );

        return isset($postIds[0]) ? (int) $postIds[0] : null;
    }

    /** @return array<string, mixed> */
    private function remoteEntry(
        string $type,
        int $remoteId,
        int $remoteChanged,
        string $title
    ): array {
        return [
            'id' => $remoteId,
            'source' => 'website',
            'remoteID' => $remoteId,
            'remoteChanged' => $remoteChanged,
            'title' => ['rendered' => $title],
            'content' => ['rendered' => '<p><a href="relative-link">Content</a></p>'],
            'lang' => 'en',
            'link' => self::SOURCE_URL . '/' . $type . '/' . $remoteId,
            'rrze_' . $type . '_category' => ['Imported category'],
            'rrze_' . $type . '_tag' => ['Imported tag'],
        ];
    }

    /**
     * @param mixed $body
     * @return array<string, mixed>
     */
    private function httpResponse($body, int $statusCode, int $totalPages): array
    {
        return [
            'headers' => ['x-wp-totalpages' => (string) $totalPages],
            'body' => is_string($body) ? $body : (string) wp_json_encode($body),
            'response' => [
                'code' => $statusCode,
                'message' => $statusCode === 200 ? 'OK' : 'Error',
            ],
            'cookies' => [],
            'filename' => null,
        ];
    }

    /** @param array<int, array<string, mixed>|WP_Error> $pages */
    private function mockHttpPages(array $pages): void
    {
        $this->mockHttpRequests(
            static function (array $requestArguments, string $url) use ($pages) {
                $query = (string) wp_parse_url($url, PHP_URL_QUERY);
                parse_str($query, $queryArguments);
                $page = isset($queryArguments['page']) ? (int) $queryArguments['page'] : 1;

                return $pages[$page] ?? new WP_Error(
                    'unexpected_page',
                    'The sync requested an unexpected remote page.'
                );
            }
        );
    }

    /**
     * @param callable(array<string, mixed>, string): (array<string, mixed>|WP_Error) $response
     */
    private function mockHttpRequests(callable $response): void
    {
        add_filter(
            'pre_http_request',
            static function ($preempt, array $requestArguments, string $url) use ($response) {
                unset($preempt);

                return $response($requestArguments, $url);
            },
            10,
            3
        );
    }
}
