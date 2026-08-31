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

    protected function tearDown(): void
    {
        foreach ($this->temporaryBlocks as $blockName) {
            if (WP_Block_Type_Registry::get_instance()->is_registered($blockName)) {
                unregister_block_type($blockName);
            }
        }

        $this->temporaryBlocks = [];
        remove_all_filters('rrze_answers_accordion_backend');
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

    /** @dataProvider explicitShortcodeProvider */
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
        self::assertMatchesRegularExpression('/<h2 id="rrze-answers-index-\d+-A">A<\/h2>/', $output);
    }

    /** @return array<string, array{string, string, string}> */
    public function explicitShortcodeProvider(): array
    {
        return [
            'FAQ' => ['rrze_faq', 'faq', 'glossary'],
            'Glossary' => ['rrze_glossary', 'glossary', 'register'],
        ];
    }

    /** @dataProvider groupedShortcodeProvider */
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
