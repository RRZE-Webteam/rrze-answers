<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use WP_Query;
use WP_UnitTestCase;

final class ListViewTest extends WP_UnitTestCase
{
    /** @var array<string, int> */
    private array $remotePosts = [];

    public function set_up(): void
    {
        parent::set_up();

        $_GET = [];
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        // add_option() bypasses the settings-form pre-update sanitizer, which
        // intentionally rebuilds registeredDomains from submitted form data.
        delete_option('rrze-answers');
        add_option(
            'rrze-answers',
            [
                'registeredDomains' => [
                    'remote' => 'https://remote.example.org',
                ],
            ]
        );

        foreach (['rrze_faq', 'rrze_glossary', 'rrze_synonym'] as $postType) {
            $this->remotePosts[$postType] = $this->createPost(
                $postType,
                [
                    'source' => 'remote',
                    'lang' => 'en',
                    'sortfield' => 'Zulu remote',
                    'synonym' => 'Zulu remote full form',
                    'titleLang' => 'en',
                ]
            );
        }
    }

    public function tear_down(): void
    {
        $_GET = [];
        set_current_screen('front');

        parent::tear_down();
    }

    /** @dataProvider qaPostTypes */
    public function testQaListUsesStableSortableColumns(string $postType): void
    {
        set_current_screen('edit-' . $postType);

        $sortable = apply_filters('manage_edit-' . $postType . '_sortable_columns', []);

        self::assertSame('lang', $sortable['lang']);
        self::assertSame('sortfield', $sortable['sortfield']);
        self::assertSame('source', $sortable['source']);
        self::assertSame(
            [],
            array_filter(
                array_keys($sortable),
                static fn ($column): bool => str_starts_with((string) $column, 'taxonomy-')
            )
        );
    }

    /** @dataProvider qaSortingScenarios */
    public function testQaMetaSortingPreservesLegacyPosts(
        string $postType,
        string $orderBy
    ): void {
        [$legacyPost, $localPost] = $this->createLegacyAndLocalPosts($postType);

        $query = $this->adminQuery(
            $postType,
            [
                'orderby' => $orderBy,
                'order' => 'ASC',
                'post__in' => [
                    $legacyPost,
                    $localPost,
                    $this->remotePosts[$postType],
                ],
            ]
        );

        self::assertEqualsCanonicalizing(
            [$legacyPost, $localPost, $this->remotePosts[$postType]],
            $query->posts
        );
    }

    /** @dataProvider allPostTypes */
    public function testWebsiteSourceIncludesLegacyAndExplicitLocalPosts(string $postType): void
    {
        [$legacyPost, $localPost] = $this->createLegacyAndLocalPosts($postType);

        $query = $this->adminQuery(
            $postType,
            [
                'post__in' => [
                    $legacyPost,
                    $localPost,
                    $this->remotePosts[$postType],
                ],
            ],
            ['rrze_answers_source' => 'website']
        );

        self::assertEqualsCanonicalizing([$legacyPost, $localPost], $query->posts);
    }

    /** @dataProvider allPostTypes */
    public function testRemoteSourceFindsOnlyImportedPosts(string $postType): void
    {
        [$legacyPost, $localPost] = $this->createLegacyAndLocalPosts($postType);

        $query = $this->adminQuery(
            $postType,
            [
                'post__in' => [
                    $legacyPost,
                    $localPost,
                    $this->remotePosts[$postType],
                ],
            ],
            ['rrze_answers_source' => 'remote']
        );

        self::assertSame([$this->remotePosts[$postType]], $query->posts);
    }

    /** @dataProvider allPostTypes */
    public function testSourceFilterIsAvailableForEveryPostType(string $postType): void
    {
        $html = $this->renderFilters($postType);

        self::assertStringContainsString('rrze_answers_source', $html);
        self::assertStringContainsString('value="website"', $html);
        self::assertStringContainsString('value="remote"', $html);
    }

    /** @dataProvider qaPostTypes */
    public function testEmptyTaxonomiesDoNotRenderFilterBoxes(string $postType): void
    {
        $html = $this->renderFilters($postType);

        self::assertStringNotContainsString('name="' . $postType . '_category"', $html);
        self::assertStringNotContainsString('name="' . $postType . '_tag"', $html);
    }

    /** @dataProvider qaPostTypes */
    public function testUsedTaxonomiesRenderAndFilterTheList(string $postType): void
    {
        $localPost = $this->createPost($postType, ['source' => 'website']);
        $otherPost = $this->createPost($postType, ['source' => 'website']);
        $category = wp_insert_term('Used category', $postType . '_category');
        $tag = wp_insert_term('Used tag', $postType . '_tag');

        self::assertIsArray($category);
        self::assertIsArray($tag);

        wp_set_object_terms($localPost, [(int) $category['term_id']], $postType . '_category');
        wp_set_object_terms($localPost, [(int) $tag['term_id']], $postType . '_tag');

        $html = $this->renderFilters($postType);

        self::assertStringContainsString("name='" . $postType . "_category'", $html);
        self::assertStringContainsString("name='" . $postType . "_tag'", $html);

        $query = $this->adminQuery(
            $postType,
            ['post__in' => [$localPost, $otherPost]],
            [
                'rrze_answers_source' => 'website',
                $postType . '_category' => (string) $category['term_id'],
                $postType . '_tag' => (string) $tag['term_id'],
            ]
        );

        self::assertSame([$localPost], $query->posts);
    }

    public function testQaColumnsHaveFallbacksForLegacyPosts(): void
    {
        $legacyPost = $this->createPost('rrze_faq');

        self::assertSame('website', $this->renderColumn('rrze_faq', 'source', $legacyPost));
        self::assertStringStartsWith(
            substr(get_locale(), 0, 2),
            $this->renderColumn('rrze_faq', 'lang', $legacyPost)
        );
    }

    public function testSynonymListProvidesCompleteColumnsAndStableSorting(): void
    {
        [$legacyPost, $localPost] = $this->createLegacyAndLocalPosts('rrze_synonym');

        set_current_screen('edit-rrze_synonym');

        $columns = apply_filters(
            'manage_rrze_synonym_posts_columns',
            ['cb' => 'Checkbox', 'title' => 'Title', 'date' => 'Date']
        );
        $sortable = apply_filters('manage_edit-rrze_synonym_sortable_columns', []);

        self::assertArrayHasKey('synonym', $columns);
        self::assertArrayHasKey('titleLang', $columns);
        self::assertArrayHasKey('source', $columns);
        self::assertSame('synonym', $sortable['synonym']);
        self::assertSame('titleLang', $sortable['titleLang']);
        self::assertSame('source', $sortable['source']);
        self::assertSame(
            'Alpha full form',
            $this->renderColumn('rrze_synonym', 'synonym', $localPost)
        );

        foreach (['synonym', 'titleLang', 'source'] as $orderBy) {
            $query = $this->adminQuery(
                'rrze_synonym',
                [
                    'orderby' => $orderBy,
                    'post__in' => [
                        $legacyPost,
                        $localPost,
                        $this->remotePosts['rrze_synonym'],
                    ],
                ]
            );

            self::assertEqualsCanonicalizing(
                [$legacyPost, $localPost, $this->remotePosts['rrze_synonym']],
                $query->posts
            );
        }
    }

    /** @return array<string, array{string}> */
    public static function qaPostTypes(): array
    {
        return [
            'FAQ' => ['rrze_faq'],
            'Glossary' => ['rrze_glossary'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function allPostTypes(): array
    {
        return [
            'FAQ' => ['rrze_faq'],
            'Glossary' => ['rrze_glossary'],
            'Synonym' => ['rrze_synonym'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function qaSortingScenarios(): array
    {
        $scenarios = [];

        foreach (['rrze_faq', 'rrze_glossary'] as $postType) {
            foreach (['sortfield', 'lang', 'source'] as $orderBy) {
                $scenarios[$postType . ' / ' . $orderBy] = [$postType, $orderBy];
            }
        }

        return $scenarios;
    }

    /** @param array<string, string> $meta */
    private function createPost(string $postType, array $meta = []): int
    {
        $postId = self::factory()->post->create(
            [
                'post_type' => $postType,
                'post_status' => 'publish',
            ]
        );

        foreach ($meta as $key => $value) {
            update_post_meta($postId, $key, $value);
        }

        return $postId;
    }

    /** @return array{int, int} */
    private function createLegacyAndLocalPosts(string $postType): array
    {
        return [
            $this->createPost($postType),
            $this->createPost(
                $postType,
                [
                    'source' => 'website',
                    'lang' => 'de',
                    'sortfield' => 'Alpha local',
                    'synonym' => 'Alpha full form',
                    'titleLang' => 'de',
                ]
            ),
        ];
    }

    /**
     * @param array<string, mixed>  $arguments
     * @param array<string, string> $filters
     */
    private function adminQuery(
        string $postType,
        array $arguments = [],
        array $filters = []
    ): WP_Query {
        $originalGet = $_GET;
        $originalMainQuery = $GLOBALS['wp_the_query'] ?? null;

        $_GET = $filters;
        set_current_screen('edit-' . $postType);

        $query = new WP_Query();
        $GLOBALS['wp_the_query'] = $query;

        $query->query(
            array_merge(
                [
                    'post_type' => $postType,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                ],
                $arguments
            )
        );

        $_GET = $originalGet;
        $GLOBALS['wp_the_query'] = $originalMainQuery;

        return $query;
    }

    private function renderFilters(string $postType): string
    {
        set_current_screen('edit-' . $postType);

        ob_start();
        do_action('restrict_manage_posts', $postType);

        return (string) ob_get_clean();
    }

    private function renderColumn(string $postType, string $column, int $postId): string
    {
        set_current_screen('edit-' . $postType);

        ob_start();
        do_action('manage_' . $postType . '_posts_custom_column', $column, $postId);

        return trim(wp_strip_all_tags((string) ob_get_clean()));
    }
}
