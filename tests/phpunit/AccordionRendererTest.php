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

    /**
     * Elements retains its mapped state and loads the Answers keyboard adapter.
     */
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
        wp_dequeue_script('rrze-answers-accordion');
        $output = AccordionRenderer::renderCollections('glossary', $content, 3, true, true);

        self::assertTrue(wp_script_is('rrze-answers-accordion', 'enqueued'));
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
     * Large lists and individual answers must render without raising PCRE limits.
     *
     * @dataProvider largeCollectionCases
     * @param string $backend Accordion implementation to exercise.
     * @param int $itemCount Number of consecutive entries.
     * @param int $answerRepeats Repetitions used to build each synthetic answer.
     */
    public function testLargeCollectionsRenderEveryItem(string $backend, int $itemCount, int $answerRepeats): void
    {
        $this->useCollectionBackend($backend);
        $content = '';
        for ($index = 0; $index < $itemCount; $index++) {
            $content .= AccordionRenderer::renderItem(
                'faq',
                'large-question-' . $index,
                'Question ' . $index,
                '<p>Answer ' . $index . ': ' . str_repeat('Synthetic answer. ', $answerRepeats) . '</p>',
                '',
                $index === 0
            ) . "\n";
        }
        self::assertGreaterThan(1000000, strlen($content));

        $previousLimit = ini_set('pcre.backtrack_limit', '1000000');
        self::assertNotFalse($previousLimit);
        try {
            $output = AccordionRenderer::renderCollections('faq', $content);
        } finally {
            ini_set('pcre.backtrack_limit', $previousLimit);
        }

        self::assertFalse(AccordionRenderer::hasBlockItems($output));
        $itemClass = $backend === AccordionRenderer::BACKEND_ELEMENTS
            ? 'class="elements-collapse"'
            : 'class="wp-block-accordion-item ';
        self::assertSame($itemCount, substr_count($output, $itemClass));
        self::assertStringContainsString('Answer 0:', $output);
        self::assertStringContainsString('Answer ' . ($itemCount - 1) . ':', $output);
        self::assertStringContainsString('class="rrze-answers-schema"', $output);
        self::assertStringNotContainsString('<!-- rrze-answers-schema:', $output);
    }

    /**
     * Exercise both block backends with many short entries and one long entry.
     *
     * @return array<string, array{string, int, int}> Backend, count and answer size.
     */
    public static function largeCollectionCases(): array
    {
        return [
            'elements, many entries' => [AccordionRenderer::BACKEND_ELEMENTS, 1000, 50],
            'core, many entries' => [AccordionRenderer::BACKEND_CORE, 1000, 50],
            'elements, long answer' => [AccordionRenderer::BACKEND_ELEMENTS, 1, 70000],
            'core, long answer' => [AccordionRenderer::BACKEND_CORE, 1, 70000],
        ];
    }

    /**
     * Keep item order, whitespace-only joins and group boundaries when scanning.
     * Only the first item across all collections may inherit load_open.
     */
    public function testCollectionBoundariesPreserveOrderAndInitialState(): void
    {
        $this->useCollectionBackend(AccordionRenderer::BACKEND_ELEMENTS);
        $items = [];
        $collections = 0;
        /**
         * Observe rendered block attributes without changing their values.
         *
         * @param array<string, mixed> $block Parsed block supplied by WordPress.
         * @return array<string, mixed> Unchanged block.
         */
        $capture = static function (array $block) use (&$items, &$collections): array {
            if ($block['blockName'] === 'rrze-elements/collapse') {
                $items[] = $block['attrs'];
            } elseif ($block['blockName'] === 'rrze-elements/collapsibles') {
                $collections++;
            }
            return $block;
        };
        add_filter('render_block_data', $capture);
        $separator = '</section><h2>Second group</h2><section>';
        $incompleteItem = '<!-- rrze-answers-accordion-item:start -->unfinished';
        try {
            $first = AccordionRenderer::renderItem('faq', 'one', 'One', '<p>First answer</p>', '', false);
            $second = AccordionRenderer::renderItem('faq', 'two', 'Two', '<p>Second answer</p>', '', false);
            $third = AccordionRenderer::renderItem('faq', 'three', 'Three', '<p>Third answer</p>', '', false);
            $output = AccordionRenderer::renderCollections(
                'faq',
                '<section>' . $first . " \t\r\n" . $second . $separator . $third . '</section>' . $incompleteItem,
                3,
                true,
                true
            );
        } finally {
            remove_filter('render_block_data', $capture);
        }

        self::assertSame(2, $collections);
        self::assertSame(['one', 'two', 'three'], array_column($items, 'jumpName'));
        self::assertSame([true, false, false], array_column($items, 'loadOpen'));
        self::assertStringContainsString($separator, $output);
        self::assertStringEndsWith('</section>' . $incompleteItem, $output);
        self::assertLessThan(strpos($output, $separator), strpos($output, 'Second answer'));
        self::assertGreaterThan(strpos($output, $separator), strpos($output, 'Third answer'));
    }

    /**
     * Select Core or register lightweight Elements blocks for renderer tests.
     *
     * @param string $backend Requested block implementation.
     */
    private function useCollectionBackend(string $backend): void
    {
        if ($backend === AccordionRenderer::BACKEND_ELEMENTS) {
            $this->registerTemporaryBlock(
                'rrze-elements/collapse',
                /**
                 * Render an observable Elements item around its unchanged answer.
                 *
                 * @param array<string, mixed> $attributes Parsed block attributes.
                 * @param string $content Rendered inner content.
                 * @return string Item HTML.
                 */
                static function (array $attributes, string $content): string {
                    return '<article class="elements-collapse">' . $content . '</article>';
                },
                ['loadOpen' => ['type' => 'boolean', 'default' => false]]
            );
            $this->registerTemporaryBlock(
                'rrze-elements/collapsibles',
                /**
                 * Render a collection around its unchanged items.
                 *
                 * @param array<string, mixed> $attributes Parsed block attributes.
                 * @param string $content Rendered items.
                 * @return string Collection HTML.
                 */
                static function (array $attributes, string $content): string {
                    return '<div class="elements-collapsibles">' . $content . '</div>';
                },
                []
            );
        }
        /**
         * Force the backend under test without changing the installed plugins.
         *
         * @return string Requested backend name.
         */
        $selectBackend = static fn (): string => $backend;
        add_filter('rrze_answers_accordion_backend', $selectBackend);
        self::assertSame($backend, AccordionRenderer::getBackend());
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
