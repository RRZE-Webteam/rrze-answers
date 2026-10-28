<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Tools;
use RRZE\Answers\Common\TabsRenderer;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

final class IndexNavigationTest extends WP_UnitTestCase
{
    /** @var string[] */
    private array $temporaryBlocks = [];

    /**
     * Restore temporary registrations and render filters after each example.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryBlocks as $blockName) {
            if (WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                unregister_block_type($blockName);
            }
        }

        $this->temporaryBlocks = [];
        remove_all_filters('rrze_answers_accordion_backend');
        wp_dequeue_script('rrze-answers-index');
        parent::tearDown();
    }

    public function testAlphabeticalNavigationRendersForOneLetterWithUniquePrefix(): void
    {
        $output = Tools::createAZ(['A' => true], 'answers-index');

        self::assertStringContainsString('class="letters list-icons"', $output);
        self::assertStringContainsString('href="#answers-index-A"', $output);
        self::assertStringContainsString('<li class="filled">', $output);
    }

    public function testTabsFallBackToGroupedContentWhenElementsBlocksAreUnavailable(): void
    {
        self::assertFalse(TabsRenderer::isAvailable());

        $output = TabsRenderer::createCollection([
            ['title' => 'General', 'content' => '<p>Definition</p>'],
        ]);

        self::assertSame('<p>Definition</p>', $output);
        self::assertStringNotContainsString('rrze-elements/tabs', $output);
    }

    public function testTagcloudRendersDirectlyStyledLinksForOneTerm(): void
    {
        $terms = [
            'General' => ['ID' => 12, 'letter' => 'G'],
        ];

        $output = Tools::createTagcloud($terms, [12 => [100]], 'answers-term');

        self::assertStringContainsString('class="rrze-answers-tagcloud"', $output);
        self::assertStringContainsString('href="#answers-term-12"', $output);
        self::assertStringContainsString('class="rrze-answers-tagcloud-item"', $output);
        self::assertStringContainsString('--rrze-answers-tagcloud-size:22px', $output);
    }

    /**
     * Verify explicit selections expose both current and legacy letter targets.
     *
     * @dataProvider explicitShortcodeProvider
     * @param string $postType Registered entry post type.
     * @param string $shortcode Public shortcode name.
     * @param string $styleAttribute Shortcode index attribute.
     * @return void
     */
    public function testAlphabeticalNavigationAppliesToExplicitEntries(
        string $postType,
        string $shortcode,
        string $styleAttribute
    ): void {
        $postId = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Alpha entry',
            'post_content' => 'Definition',
        ]);

        $output = do_shortcode(
            sprintf('[%s id="%d" %s="a-z"]', $shortcode, $postId, $styleAttribute)
        );

        self::assertStringContainsString('class="letters list-icons"', $output);
        self::assertMatchesRegularExpression('/href="#rrze-answers-index-\d+-A"/', $output);
        self::assertMatchesRegularExpression(
            '/<h2 id="rrze-answers-index-\d+-A" data-rrze-answers-legacy-anchor="letter-A">A<\/h2>/',
            $output
        );
        self::assertTrue(wp_script_is('rrze-answers-index', 'enqueued'));
    }

    /**
     * Keep repeated lists addressable without claiming an old global ID.
     *
     * @dataProvider explicitShortcodeProvider
     * @param string $postType Registered entry post type.
     * @param string $shortcode Public shortcode name.
     * @param string $styleAttribute Shortcode index attribute.
     * @return void
     */
    public function testRepeatedListsRetainUniqueIdsAndSharedLegacyMappings(
        string $postType,
        string $shortcode,
        string $styleAttribute
    ): void {
        $postId = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Alpha entry',
            'post_content' => 'Definition',
        ]);
        $list = sprintf('[%s id="%d" %s="a-z" hide="accordion"]', $shortcode, $postId, $styleAttribute);

        $output = do_shortcode($list . $list);
        preg_match_all('/<h2 id="([^"]+)" data-rrze-answers-legacy-anchor="letter-A">/', $output, $targets);

        self::assertCount(2, $targets[1]);
        self::assertCount(2, array_unique($targets[1]));
        self::assertStringNotContainsString('id="letter-A"', $output);
        foreach ($targets[1] as $id) {
            self::assertStringContainsString('href="#' . $id . '"', $output);
        }
    }

    /**
     * Keep query order while assigning only one target to a repeated letter.
     *
     * @dataProvider explicitShortcodeProvider
     * @param string $postType Registered entry post type.
     * @param string $shortcode Public shortcode name.
     * @param string $styleAttribute Shortcode index attribute.
     * @return void
     */
    public function testRepeatedLettersDoNotDuplicateTargetsInQueryOrder(
        string $postType,
        string $shortcode,
        string $styleAttribute
    ): void {
        $taxonomy = $postType . '_category';
        $term = wp_insert_term('Index order', $taxonomy, ['slug' => 'index-order']);
        self::assertIsArray($term);
        foreach (['Alpha entry', 'Beta entry', 'Another entry'] as $position => $title) {
            $postId = self::factory()->post->create([
                'post_type' => $postType,
                'post_status' => 'publish',
                'post_title' => $title,
                'post_content' => 'Definition',
            ]);
            update_post_meta($postId, 'sortfield', (string) $position);
            wp_set_object_terms($postId, [(int) $term['term_id']], $taxonomy);
        }

        $output = do_shortcode(sprintf(
            '[%s category="index-order" sort="sortfield" order="asc" %s="a-z" hide="accordion"]',
            $shortcode,
            $styleAttribute
        ));
        preg_match_all('/<h2[^>]*>([AB])<\/h2>/', $output, $headings);

        self::assertSame(['A', 'B', 'A'], $headings[1]);
        self::assertSame(1, substr_count($output, 'data-rrze-answers-legacy-anchor="letter-A"'));
        self::assertSame(1, substr_count($output, 'data-rrze-answers-legacy-anchor="letter-B"'));
        self::assertStringContainsString('<h2>A</h2>', $output);
        self::assertLessThan(strpos($output, 'Beta entry'), strpos($output, 'Alpha entry'));
        self::assertLessThan(strpos($output, 'Another entry'), strpos($output, 'Beta entry'));
    }

    /**
     * Preserve custom entry IDs beside group mappings, including tabs fallback.
     *
     * @dataProvider explicitShortcodeProvider
     * @param string $postType Registered entry post type.
     * @param string $shortcode Public shortcode name.
     * @param string $styleAttribute Shortcode index attribute.
     * @return void
     */
    public function testGroupMappingsDoNotClaimCustomEntryAnchors(
        string $postType,
        string $shortcode,
        string $styleAttribute
    ): void {
        add_filter('rrze_answers_accordion_backend', [$this, 'nativeAccordion']);
        $taxonomy = $postType . '_category';
        $term = wp_insert_term('Alpha group', $taxonomy, ['slug' => 'alpha-group']);
        self::assertIsArray($term);
        $termId = (int) $term['term_id'];
        $postId = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Entry',
            'post_content' => 'Definition',
        ]);
        wp_set_object_terms($postId, [$termId], $taxonomy);

        foreach (['tagcloud' => 'ID-' . $termId, 'a-z' => 'letter-A', 'tabs' => 'ID-' . $termId] as $style => $anchor) {
            // Each style is an independent page, not an additional list on one page.
            (new \ReflectionProperty(\RRZE\Answers\Common\Content\QuestionListRenderer::class, 'usedAnchorIds'))->setValue(null, []);
            update_post_meta($postId, 'anchorfield', $anchor);
            $output = do_shortcode(sprintf('[%s %s="category %s"]', $shortcode, $styleAttribute, $style));

            self::assertSame(1, substr_count($output, 'id="' . $anchor . '"'));
            self::assertStringContainsString('data-rrze-answers-legacy-anchor="ID-' . $termId . '"', $output);
            self::assertMatchesRegularExpression('/<section id="rrze-answers-index-\d+-term-' . $termId . '"/', $output);
            if ($style === 'a-z') {
                self::assertStringContainsString('data-rrze-answers-legacy-anchor="letter-A"', $output);
            }
        }
    }

    /**
     * Select native details so the test can inspect actual entry IDs.
     *
     * @return string Native accordion backend identifier.
     */
    public function nativeAccordion(): string
    {
        return 'native';
    }

    /** @return array<string, array{string, string, string}> */
    public function explicitShortcodeProvider(): array
    {
        return [
            'FAQ' => ['rrze_faq', 'faq', 'glossary'],
            'Glossary' => ['rrze_glossary', 'glossary', 'register'],
        ];
    }

    /**
     * Keep legacy group targets inside their corresponding Elements tab panels.
     *
     * @dataProvider groupedShortcodeProvider
     * @param string $postType Registered entry post type.
     * @param string $taxonomy Grouping taxonomy.
     * @param string $shortcode Public shortcode name.
     * @param string $groupAttribute Shortcode grouping attribute.
     * @return void
     */
    public function testGroupedTabsUseElementsBlocks(
        string $postType,
        string $taxonomy,
        string $shortcode,
        string $groupAttribute
    ): void {
        $renderedTabs = [];
        $renderedTabItems = [];
        $this->registerElementsTabs($renderedTabs, $renderedTabItems);
        $this->registerElementsAccordion();

        $firstTerm = wp_insert_term('Alpha group', $taxonomy);
        $secondTerm = wp_insert_term('Beta group', $taxonomy);

        self::assertIsArray($firstTerm);
        self::assertIsArray($secondTerm);

        $firstPost = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Alpha entry',
            'post_content' => 'First definition',
        ]);
        $secondPost = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Beta entry',
            'post_content' => 'Second definition',
        ]);

        wp_set_object_terms($firstPost, [(int) $firstTerm['term_id']], $taxonomy);
        wp_set_object_terms($secondPost, [(int) $secondTerm['term_id']], $taxonomy);

        $output = do_shortcode(
            sprintf('[%s %s="category tabs"]', $shortcode, $groupAttribute)
        );

        self::assertStringContainsString('class="rrze-elements-tabs"', $output);
        self::assertSame(2, substr_count($output, 'role="tabpanel"'));
        self::assertSame(2, substr_count($output, 'class="elements-collapsibles"'));
        self::assertSame(2, substr_count($output, 'class="elements-collapse"'));
        self::assertCount(1, $renderedTabs);
        self::assertCount(2, $renderedTabItems);
        self::assertSame('Alpha group', $renderedTabs[0]['innerClientIds'][0]['title']);
        self::assertSame('Beta group', $renderedTabs[0]['innerClientIds'][1]['title']);
        self::assertSame($renderedTabs[0]['active'], $renderedTabs[0]['innerClientIds'][0]['clientId']);
        self::assertSame($renderedTabs[0]['blockId'], $renderedTabItems[0]['tabsUid']);
        self::assertStringContainsString('First definition', $output);
        self::assertStringContainsString('Second definition', $output);
        foreach ([$firstTerm['term_id'], $secondTerm['term_id']] as $termId) {
            self::assertMatchesRegularExpression(
                '/<div role="tabpanel"[^>]*><div id="rrze-answers-index-\d+-term-' . $termId
                . '" data-rrze-answers-legacy-anchor="ID-' . $termId . '" class="answers-term-content">/',
                $output
            );
            self::assertStringNotContainsString('id="ID-' . $termId . '"', $output);
        }
        self::assertTrue(wp_script_is('rrze-answers-index', 'enqueued'));
    }

    /** @return array<string, array{string, string, string, string}> */
    public function groupedShortcodeProvider(): array
    {
        return [
            'FAQ' => ['rrze_faq', 'rrze_faq_category', 'faq', 'glossary'],
            'Glossary' => ['rrze_glossary', 'rrze_glossary_category', 'glossary', 'register'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $renderedTabs
     * @param array<int, array<string, mixed>> $renderedTabItems
     */
    private function registerElementsTabs(
        array &$renderedTabs,
        array &$renderedTabItems
    ): void {
        register_block_type(
            'rrze-elements/tab',
            [
                'api_version' => 3,
                'attributes' => [
                    'title' => ['type' => 'string', 'default' => ''],
                    'order' => ['type' => 'integer', 'default' => 0],
                    'active' => ['type' => 'boolean', 'default' => false],
                    'blockId' => ['type' => 'string', 'default' => ''],
                    'tabsUid' => ['type' => 'string', 'default' => ''],
                    'className' => ['type' => 'string', 'default' => ''],
                ],
                'render_callback' => static function (
                    array $attributes,
                    string $content
                ) use (&$renderedTabItems): string {
                    $renderedTabItems[] = $attributes;

                    return '<div role="tabpanel" data-title="'
                        . esc_attr($attributes['title']) . '">'
                        . $content . '</div>';
                },
            ]
        );
        $this->temporaryBlocks[] = 'rrze-elements/tab';

        register_block_type(
            'rrze-elements/tabs',
            [
                'api_version' => 3,
                'attributes' => [
                    'blockId' => ['type' => 'string', 'default' => ''],
                    'innerClientIds' => ['type' => 'array', 'default' => []],
                    'active' => ['type' => 'string', 'default' => ''],
                    'color' => ['type' => 'string', 'default' => ''],
                    'className' => ['type' => 'string', 'default' => ''],
                ],
                'render_callback' => static function (
                    array $attributes,
                    string $content
                ) use (&$renderedTabs): string {
                    $renderedTabs[] = $attributes;

                    return '<div class="rrze-elements-tabs">' . $content . '</div>';
                },
            ]
        );
        $this->temporaryBlocks[] = 'rrze-elements/tabs';
    }

    private function registerElementsAccordion(): void
    {
        register_block_type(
            'rrze-elements/collapse',
            [
                'api_version' => 3,
                'attributes' => [
                    'loadOpen' => ['type' => 'boolean', 'default' => false],
                    'title' => ['type' => 'string', 'default' => ''],
                    'jumpName' => ['type' => 'string', 'default' => ''],
                    'hstart' => ['type' => 'integer', 'default' => 2],
                    'color' => ['type' => 'string', 'default' => ''],
                    'className' => ['type' => 'string', 'default' => ''],
                ],
                'render_callback' => static fn (
                    array $attributes,
                    string $content
                ): string => '<article class="elements-collapse">'
                    . esc_html($attributes['title']) . $content . '</article>',
            ]
        );
        $this->temporaryBlocks[] = 'rrze-elements/collapse';

        register_block_type(
            'rrze-elements/collapsibles',
            [
                'api_version' => 3,
                'attributes' => [
                    'expandAllLink' => ['type' => 'boolean', 'default' => false],
                    'expandLabel' => ['type' => 'string', 'default' => ''],
                    'hstart' => ['type' => 'integer', 'default' => 2],
                ],
                'render_callback' => static fn (
                    array $attributes,
                    string $content
                ): string => '<div class="elements-collapsibles">'
                    . $content . '</div>',
            ]
        );
        $this->temporaryBlocks[] = 'rrze-elements/collapsibles';
    }
}
