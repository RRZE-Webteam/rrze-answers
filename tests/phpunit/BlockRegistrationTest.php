<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

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
}
