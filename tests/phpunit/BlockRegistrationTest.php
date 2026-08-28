<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Blocks\BlockAttributes;
use WP_Block;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

final class BlockRegistrationTest extends WP_UnitTestCase
{
    /** @var string[] */
    private const BLOCK_NAMES = [
        'rrze-answers/faq',
        'rrze-answers/faq-widget',
        'rrze-answers/glossary',
        'rrze-answers/placeholder',
        'rrze-answers/synonym',
    ];

    public function testGeneratedBlockCollectionIsRegistered(): void
    {
        $registry = WP_Block_Type_Registry::get_instance();

        foreach (self::BLOCK_NAMES as $blockName) {
            $block = $registry->get_registered($blockName);

            self::assertInstanceOf(WP_Block_Type::class, $block, $blockName);
            self::assertIsCallable($block->render_callback, $blockName);
            self::assertNotEmpty($block->editor_script_handles, $blockName);
        }
    }

    public function testSharedBlockAssetsAreRegistered(): void
    {
        self::assertTrue(wp_style_is('rrze-answers-css', 'registered'));
        self::assertTrue(wp_style_is('rrze-answers-admin-css', 'registered'));
        self::assertTrue(wp_script_is('rrze-answers-accordion', 'registered'));
        self::assertTrue(wp_script_is('rrze-answers-search', 'registered'));
    }

    public function testBlockInserterCategoryIsRegisteredOnce(): void
    {
        $categories = apply_filters('block_categories_all', []);
        $rrzeCategories = array_filter(
            $categories,
            static fn (array $category): bool => ($category['slug'] ?? '') === 'rrze'
        );

        self::assertCount(1, $rrzeCategories);
        self::assertSame('RRZE Answers', reset($rrzeCategories)['title']);
    }

    public function testFilterBlocksExposeTypedAttributesAndDefaults(): void
    {
        $registry = WP_Block_Type_Registry::get_instance();

        foreach (['rrze-answers/faq', 'rrze-answers/glossary'] as $blockName) {
            $block = $registry->get_registered($blockName);

            self::assertSame('array', $block->attributes['id']['type']);
            self::assertSame('integer', $block->attributes['id']['items']['type']);
            self::assertSame([], $block->attributes['id']['default']);
            self::assertSame('array', $block->attributes['category']['type']);
            self::assertSame('string', $block->attributes['category']['items']['type']);
            self::assertSame([], $block->attributes['category']['default']);
            self::assertSame(false, $block->attributes['hide_title']['default']);
            self::assertSame('title', $block->attributes['sort']['default']);
            self::assertSame('ASC', $block->attributes['order']['default']);
        }
    }

    public function testLegacyDelimiterAttributesAreAvailableToServerNormalization(): void
    {
        $parsedBlock = [
            'blockName' => 'rrze-answers/faq',
            'attrs' => [
                'category' => 'general, students',
                'id' => '10,20',
                'hide_title' => 1,
            ],
            'innerBlocks' => [],
            'innerHTML' => '',
            'innerContent' => [],
        ];
        $block = new WP_Block($parsedBlock);

        $attributes = BlockAttributes::normalize(
            [
                'category' => [],
                'id' => [],
                'hide_title' => false,
            ],
            $block,
            ['category'],
            ['id'],
            ['hide_title']
        );

        self::assertSame(['general', 'students'], $attributes['category']);
        self::assertSame([10, 20], $attributes['id']);
        self::assertTrue($attributes['hide_title']);
    }

    public function testLegacyFaqBlockAttributesSurviveDynamicRendering(): void
    {
        $interceptShortcode = static function ($return, string $tag, array $attributes) {
            if ($tag !== 'faq') {
                return $return;
            }

            return wp_json_encode($attributes);
        };
        add_filter('pre_do_shortcode_tag', $interceptShortcode, 10, 3);

        try {
            $output = do_blocks(
                '<!-- wp:rrze-answers/faq ' .
                '{"category":"general,students","id":"10,20","hide_title":true}' .
                ' /-->'
            );
        } finally {
            remove_filter('pre_do_shortcode_tag', $interceptShortcode, 10);
        }

        $shortcodeAttributes = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('general, students', $shortcodeAttributes['category']);
        self::assertSame('10, 20', $shortcodeAttributes['id']);
        self::assertSame('1', $shortcodeAttributes['hide_title']);
    }
}
