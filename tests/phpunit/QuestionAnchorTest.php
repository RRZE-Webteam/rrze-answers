<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Content\QuestionListRenderer;
use WP_UnitTestCase;

/** Verify unique, compatible entry anchors through public blocks and shortcodes. */
final class QuestionAnchorTest extends WP_UnitTestCase
{
    /** @var string[] Temporary dependency blocks registered by this test. */
    private array $temporaryBlocks = [];

    /**
     * Start a fresh simulated page request for each example.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        (new \ReflectionProperty(QuestionListRenderer::class, 'usedAnchorIds'))->setValue(null, []);
    }

    /**
     * Remove dependency fixtures and request state after each example.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryBlocks as $name) {
            unregister_block_type($name);
        }
        remove_all_filters('rrze_answers_accordion_backend');
        (new \ReflectionProperty(QuestionListRenderer::class, 'usedAnchorIds'))->setValue(null, []);
        parent::tearDown();
    }

    /**
     * Keep every occurrence addressable across grouped shortcodes and blocks.
     *
     * @dataProvider groupedCases
     * @param string $type FAQ or glossary content type.
     * @param string $grouping Category or tag grouping.
     * @param string $backend Accordion backend to exercise.
     * @param bool $custom Whether an editor supplied a custom anchor.
     * @return void
     */
    public function testGroupedEntriesAcrossRepeatedLists(string $type, string $grouping, string $backend, bool $custom): void
    {
        $this->useBackend($backend);
        $id = $this->createEntry($type, $custom ? 'application-deadline' : '');
        $taxonomy = 'rrze_' . $type . '_' . $grouping;
        $terms = [];
        foreach (['Alpha', 'Beta'] as $name) {
            $terms[] = self::factory()->term->create(['taxonomy' => $taxonomy, 'name' => $name]);
        }
        wp_set_object_terms($id, $terms, $taxonomy);
        $groupAttribute = $type === 'faq' ? 'glossary' : 'register';
        $shortcode = '[' . $type . ' ' . $groupAttribute . '="' . $grouping . '"]';
        $output = do_shortcode($shortcode);
        $output .= do_blocks('<!-- wp:rrze-answers/' . $type . ' ' . wp_json_encode([$groupAttribute => $grouping]) . ' /-->');
        $output .= do_shortcode($shortcode);

        $base = $custom ? 'application-deadline' : 'innerID-' . $id;
        $firstContext = $base . '-' . $grouping . '-' . $terms[0];
        $secondContext = $base . '-' . $grouping . '-' . $terms[1];
        self::assertSame([
            $base, $secondContext,
            $firstContext, $secondContext . '-2',
            $firstContext . '-2', $secondContext . '-3',
        ], $this->entryAnchors($output, $backend));
        $this->assertUniqueIdsAndControls($output);
    }

    /**
     * Exercise both public content types, taxonomy modes and all accordion backends.
     *
     * @return array<string, array{string, string, string, bool}> Rendering cases.
     */
    public static function groupedCases(): array
    {
        $cases = [];
        foreach (['faq', 'glossary'] as $type) {
            foreach (['category', 'tag'] as $grouping) {
                foreach (['native', 'core', 'elements'] as $backend) {
                    foreach ([false, true] as $custom) {
                        $cases[implode('-', [$type, $grouping, $backend, $custom ? 'custom' : 'default'])]
                            = [$type, $grouping, $backend, $custom];
                    }
                }
            }
        }
        return $cases;
    }

    /**
     * Generated suffixes must also avoid explicitly assigned anchors across types.
     *
     * @return void
     */
    public function testManualAnchorsAndGeneratedSuffixesShareOneRegistry(): void
    {
        $this->useBackend('native');
        $output = '';
        foreach (['shared', 'shared-2', 'shared', 'shared'] as $index => $anchor) {
            $type = $index % 2 === 0 ? 'faq' : 'glossary';
            $id = $this->createEntry($type, $anchor);
            $output .= do_shortcode('[' . $type . ' id="' . $id . '"]');
        }
        self::assertSame(['shared', 'shared-2', 'shared-3', 'shared-4'], $this->entryAnchors($output, 'native'));
        $this->assertUniqueIdsAndControls($output);
    }

    /**
     * Preserve custom spelling and avoid collisions with Elements' derived panel IDs.
     *
     * @return void
     */
    public function testElementsPreservesAnchorsAndAvoidsPanelIdCollisions(): void
    {
        $this->useBackend('elements');
        $output = '';
        foreach (['Shared-Anchor', 'Shared-Anchor', 'Shared-Anchor-section', 'reserved-section', 'reserved'] as $anchor) {
            $id = $this->createEntry('faq', $anchor);
            $output .= do_shortcode('[faq id="' . $id . '"]');
        }
        self::assertSame([
            'Shared-Anchor', 'Shared-Anchor-2', 'Shared-Anchor-section-2',
            'reserved-section', 'reserved-2',
        ], $this->entryAnchors($output, 'elements'));
        $this->assertUniqueIdsAndControls($output);
    }

    /**
     * An outer entry precedes its nested list in the DOM and keeps the old anchor.
     * Non-accordion output must not consume an anchor reservation.
     *
     * @return void
     */
    public function testNestedEntriesReserveAnchorsInDocumentOrder(): void
    {
        $this->useBackend('native');
        $inner = $this->createEntry('glossary', 'nested-entry');
        $outer = $this->createEntry('faq', 'nested-entry');
        wp_update_post(['ID' => $outer, 'post_content' => '<p>Outer answer</p>[glossary id="' . $inner . '"]']);
        $output = do_shortcode('[glossary id="' . $inner . '" hide="accordion"]');
        $output .= do_shortcode('[faq id="' . $outer . '"]');
        self::assertSame(['nested-entry', 'nested-entry-2'], $this->entryAnchors($output, 'native'));
        $this->assertUniqueIdsAndControls($output);
    }

    /**
     * Select a real Core/native backend or a minimal Elements output contract.
     *
     * @param string $backend Requested renderer.
     * @return void
     */
    private function useBackend(string $backend): void
    {
        if ($backend === 'core' && !\WP_Block_Type_Registry::get_instance()->is_registered('core/accordion')) {
            self::markTestSkipped('This WordPress version has no Core Accordion blocks.');
        }
        if ($backend === 'elements') {
            register_block_type('rrze-elements/collapse', [
                'attributes' => [
                    'jumpName' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'className' => ['type' => 'string'],
                ],
                'render_callback' =>
                    /**
                     * Reproduce Elements Collapse's panel/control ID contract.
                     *
                     * @param array<string, mixed> $attributes Parsed item attributes.
                     * @param string $content Rendered answer.
                     * @return string Accordion item markup.
                     */
                    static function (array $attributes, string $content): string {
                        $id = esc_attr($attributes['jumpName']);
                        return '<div class="rrze-answers-block-item"><div class="accordion-group"><h3 class="accordion-heading">'
                            . '<button id="' . $id . '" class="accordion-toggle" type="button" data-href="#' . $id
                            . '" data-name="' . $id . '" aria-controls="' . $id . '-section" aria-expanded="false">'
                            . esc_html($attributes['title']) . '</button></h3><div id="' . $id
                            . '-section" class="accordion-body" role="region" aria-labelledby="' . $id . '">'
                            . $content . '</div></div></div>';
                    },
            ]);
            register_block_type('rrze-elements/collapsibles', [
                'render_callback' =>
                    /**
                     * Wrap the fixture's items in a dependency accordion collection.
                     *
                     * @param array<string, mixed> $attributes Collection attributes.
                     * @param string $content Rendered items.
                     * @return string Collection markup.
                     */
                    static fn (array $attributes, string $content): string => '<div class="accordion">' . $content . '</div>',
            ]);
            $this->temporaryBlocks = ['rrze-elements/collapse', 'rrze-elements/collapsibles'];
        }
        /**
         * Select the backend without changing the site's activated plugins.
         *
         * @return string Requested renderer.
         */
        $selectBackend = static fn (): string => $backend;
        add_filter('rrze_answers_accordion_backend', $selectBackend);
    }

    /**
     * Create a published entry with optional editor-defined anchor metadata.
     *
     * @param string $type Content type.
     * @param string $anchor Custom anchor, or empty for the default.
     * @return int New entry ID.
     */
    private function createEntry(string $type, string $anchor): int
    {
        $id = self::factory()->post->create([
            'post_type' => 'rrze_' . $type,
            'post_status' => 'publish',
            'post_title' => 'Application deadline',
            'post_content' => '<p>Apply before the deadline.</p>',
        ]);
        update_post_meta($id, 'anchorfield', $anchor);
        return $id;
    }

    /**
     * Parse generated markup while suppressing HTML5 warnings from libxml's HTML4 parser.
     *
     * @param string $output Rendered list markup.
     * @return \DOMXPath Query interface for the rendered document.
     */
    private function document(string $output): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            $document->loadHTML('<!doctype html><html><body>' . $output . '</body></html>');
            return new \DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Read actual entry targets in document order for the selected backend.
     *
     * @param string $output Rendered lists.
     * @param string $backend Selected renderer.
     * @return string[] Entry anchor IDs.
     */
    private function entryAnchors(string $output, string $backend): array
    {
        $query = match ($backend) {
            'elements' => '//button[contains(concat(" ", @class, " "), " accordion-toggle ")]',
            'core' => '//span[@class="rrze-answers-anchor"]',
            default => '//details',
        };
        $anchors = [];
        foreach ($this->document($output)->query($query) as $node) {
            $anchors[] = $node->getAttribute('id');
        }
        return $anchors;
    }

    /**
     * Validate every emitted ID and each control's accessible target relationship.
     *
     * @param string $output Rendered list markup.
     * @return void
     */
    private function assertUniqueIdsAndControls(string $output): void
    {
        $xpath = $this->document($output);
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $node) {
            $ids[] = $node->getAttribute('id');
        }
        self::assertSame($ids, array_values(array_unique($ids)), 'All rendered IDs must be unique.');
        foreach ($xpath->query('//button[contains(concat(" ", @class, " "), " accordion-toggle ")]') as $button) {
            $target = $button->getAttribute('aria-controls');
            self::assertContains($target, $ids);
            self::assertSame('#' . $button->getAttribute('id'), $button->getAttribute('data-href'));
            self::assertSame($button->getAttribute('id'), $button->getAttribute('data-name'));
            self::assertSame($button->getAttribute('id'), $xpath->document->getElementById($target)->getAttribute('aria-labelledby'));
        }
    }
}
