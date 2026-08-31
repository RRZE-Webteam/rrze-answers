<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\AccordionRenderer;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

final class AccordionRendererTest extends WP_UnitTestCase
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

    public function testNativeBackendPreservesDetailsAndMicrodata(): void
    {
        add_filter(
            'rrze_answers_accordion_backend',
            static fn (): string => AccordionRenderer::BACKEND_NATIVE
        );

        $output = AccordionRenderer::renderItem(
            'faq',
            'question-one',
            'Can I apply?',
            '<p>Yes.</p>',
            'fau',
            true,
            3
        );

        self::assertStringContainsString('<details id="question-one"', $output);
        self::assertStringContainsString('itemtype="https://schema.org/Question"', $output);
        self::assertStringContainsString('<summary itemprop="name">Can I apply?</summary>', $output);
        self::assertFalse(AccordionRenderer::hasBlockItems($output));
    }

    public function testCoreBackendRendersACollectionAndOpensOnlyTheFirstItem(): void
    {
        $registry = WP_Block_Type_Registry::get_instance();
        foreach (
            [
                'core/accordion',
                'core/accordion-item',
                'core/accordion-heading',
                'core/accordion-panel',
            ] as $blockName
        ) {
            if (!$registry->is_registered($blockName)) {
                self::markTestSkipped('The WordPress test version has no Core Accordion blocks.');
            }
        }

        add_filter(
            'rrze_answers_accordion_backend',
            static fn (): string => AccordionRenderer::BACKEND_CORE
        );

        $renderedItems = [];
        $captureItems = static function (array $parsedBlock) use (&$renderedItems): array {
            if (($parsedBlock['blockName'] ?? '') === 'core/accordion-item') {
                $renderedItems[] = $parsedBlock['attrs'];
            }

            return $parsedBlock;
        };
        add_filter('render_block_data', $captureItems);

        try {
            $content = AccordionRenderer::renderItem(
                'faq',
                'first-question',
                'First question',
                '<p>First answer</p>',
                '',
                true,
                4
            );
            $content .= AccordionRenderer::renderItem(
                'faq',
                'second-question',
                'Second question',
                '<p>Second answer</p>',
                '',
                false,
                4
            );

            $output = AccordionRenderer::renderCollections('faq', $content, 4, false, true);
        } finally {
            remove_filter('render_block_data', $captureItems);
        }

        self::assertStringContainsString('wp-block-accordion', $output);
        self::assertStringContainsString('<h4 class="wp-block-accordion-heading', $output);
        self::assertStringContainsString('First answer', $output);
        self::assertStringContainsString('id="first-question"', $output);
        self::assertStringContainsString('class="rrze-answers-schema"', $output);
        self::assertStringContainsString('"@type":"FAQPage"', $output);
        self::assertStringNotContainsString('rrze-answers-accordion-item:start', $output);
        self::assertCount(2, $renderedItems);
        self::assertTrue($renderedItems[0]['openByDefault']);
        self::assertFalse($renderedItems[1]['openByDefault']);
    }

    public function testElementsBackendHasPriorityAndReceivesMappedAttributes(): void
    {
        $renderedItems = [];
        $renderedCollections = [];

        $this->registerTemporaryBlock(
            'rrze-elements/collapse',
            static function (array $attributes, string $content) use (&$renderedItems): string {
                $renderedItems[] = $attributes;

                return '<article class="elements-collapse">' . $content . '</article>';
            },
            [
                'loadOpen' => ['type' => 'boolean', 'default' => false],
                'title' => ['type' => 'string', 'default' => ''],
                'jumpName' => ['type' => 'string', 'default' => ''],
                'hstart' => ['type' => 'integer', 'default' => 2],
                'color' => ['type' => 'string', 'default' => ''],
                'className' => ['type' => 'string', 'default' => ''],
            ]
        );
        $this->registerTemporaryBlock(
            'rrze-elements/collapsibles',
            static function (array $attributes, string $content) use (&$renderedCollections): string {
                $renderedCollections[] = $attributes;

                return '<div class="elements-collapsibles">' . $content . '</div>';
            },
            [
                'expandAllLink' => ['type' => 'boolean', 'default' => false],
                'expandLabel' => ['type' => 'string', 'default' => 'Expand All'],
                'hstart' => ['type' => 'integer', 'default' => 2],
            ]
        );

        self::assertSame(AccordionRenderer::BACKEND_ELEMENTS, AccordionRenderer::getBackend());

        $content = AccordionRenderer::renderItem(
            'glossary',
            'term-one',
            'Term one',
            '<p>Definition one</p>',
            'nat',
            false,
            3
        );
        $content .= AccordionRenderer::renderItem(
            'glossary',
            'term-two',
            'Term two',
            '<p>Definition two</p>',
            'nat',
            false,
            3
        );
        $output = AccordionRenderer::renderCollections('glossary', $content, 3, true, true);

        self::assertStringContainsString('elements-collapsibles', $output);
        self::assertCount(1, $renderedCollections);
        self::assertTrue($renderedCollections[0]['expandAllLink']);
        self::assertSame(3, $renderedCollections[0]['hstart']);
        self::assertCount(2, $renderedItems);
        self::assertTrue($renderedItems[0]['loadOpen']);
        self::assertFalse($renderedItems[1]['loadOpen']);
        self::assertSame('term-one', $renderedItems[0]['jumpName']);
        self::assertSame('nat', $renderedItems[0]['color']);
    }

    public function testFaqShortcodeUsesCoreBlocksWithoutEnqueuingTheLegacyAccordionScript(): void
    {
        $registry = WP_Block_Type_Registry::get_instance();
        if (!$registry->is_registered('core/accordion')) {
            self::markTestSkipped('The WordPress test version has no Core Accordion blocks.');
        }

        add_filter(
            'rrze_answers_accordion_backend',
            static fn (): string => AccordionRenderer::BACKEND_CORE
        );
        wp_dequeue_script('rrze-answers-accordion');

        $postId = self::factory()->post->create([
            'post_type' => 'rrze_faq',
            'post_status' => 'publish',
            'post_title' => 'Integrated question',
            'post_content' => '<p>Integrated answer</p>',
        ]);
        update_post_meta($postId, 'anchorfield', 'integrated-question');

        $output = do_shortcode('[faq id="' . $postId . '" load_open="1"]');

        self::assertStringContainsString('wp-block-accordion', $output);
        self::assertStringContainsString('Integrated question', $output);
        self::assertStringContainsString('Integrated answer', $output);
        self::assertStringNotContainsString('<details', $output);
        self::assertFalse(wp_script_is('rrze-answers-accordion', 'enqueued'));

        wp_dequeue_script('rrze-answers-accordion');
        $blockOutput = do_blocks(
            '<!-- wp:rrze-answers/faq {"id":[' . $postId . ']} /-->'
        );

        self::assertStringContainsString('wp-block-accordion', $blockOutput);
        self::assertFalse(wp_script_is('rrze-answers-accordion', 'enqueued'));
    }

    /**
     * @param callable(array<string, mixed>, string): string $renderCallback
     * @param array<string, array<string, mixed>> $attributes
     */
    private function registerTemporaryBlock(
        string $blockName,
        callable $renderCallback,
        array $attributes
    ): void {
        register_block_type(
            $blockName,
            [
                'api_version' => 3,
                'attributes' => $attributes,
                'render_callback' => $renderCallback,
            ]
        );
        $this->temporaryBlocks[] = $blockName;
    }
}
