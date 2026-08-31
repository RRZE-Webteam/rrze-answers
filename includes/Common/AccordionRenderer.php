<?php

declare(strict_types=1);

namespace RRZE\Answers\Common;

defined('ABSPATH') || exit;

final class AccordionRenderer
{
    public const BACKEND_ELEMENTS = 'elements';
    public const BACKEND_CORE = 'core';
    public const BACKEND_NATIVE = 'native';

    private const ITEM_START = '<!-- rrze-answers-accordion-item:start -->';
    private const ITEM_END = '<!-- rrze-answers-accordion-item:end -->';
    private const SCHEMA_PREFIX = '<!-- rrze-answers-schema:';

    /**
     * Select the best available accordion implementation.
     */
    public static function getBackend(): string
    {
        $registry = \WP_Block_Type_Registry::get_instance();

        if (
            $registry->is_registered('rrze-elements/collapsibles')
            && $registry->is_registered('rrze-elements/collapse')
        ) {
            $backend = self::BACKEND_ELEMENTS;
        } elseif (
            $registry->is_registered('core/accordion')
            && $registry->is_registered('core/accordion-item')
            && $registry->is_registered('core/accordion-heading')
            && $registry->is_registered('core/accordion-panel')
        ) {
            $backend = self::BACKEND_CORE;
        } else {
            $backend = self::BACKEND_NATIVE;
        }

        /**
         * Filters the accordion rendering backend.
         *
         * Returning "native" is useful when a theme needs the legacy details markup.
         * An unavailable block backend is ignored.
         *
         * @param string $backend elements, core, or native.
         */
        $filtered = apply_filters('rrze_answers_accordion_backend', $backend);
        if (!is_string($filtered) || !self::isBackendAvailable($filtered, $registry)) {
            return $backend;
        }

        return $filtered;
    }

    /**
     * Render one accordion item or serialize it for collection-level rendering.
     */
    public static function renderItem(
        string $type,
        string $anchor,
        string $question,
        string $answer,
        string $color,
        bool $useSchema,
        int $headingLevel = 2
    ): string {
        $backend = self::getBackend();

        if ($backend === self::BACKEND_NATIVE) {
            return self::renderNativeItem(
                $type,
                $anchor,
                $question,
                $answer,
                $color,
                $useSchema
            );
        }

        $schemaMarker = $useSchema
            ? self::createSchemaMarker($type, $question, $answer)
            : '';
        $item = $backend === self::BACKEND_ELEMENTS
            ? self::serializeElementsItem($anchor, $question, $answer, $color, $headingLevel)
            : self::serializeCoreItem($anchor, $question, $answer, $headingLevel);

        return self::ITEM_START . $schemaMarker . $item . self::ITEM_END;
    }

    /**
     * Render every consecutive sequence of serialized items as one accordion.
     * Raw headings and taxonomy wrappers therefore remain outside the block wrapper.
     */
    public static function renderCollections(
        string $type,
        string $content,
        int $headingLevel = 2,
        bool $expandAll = false,
        bool $loadOpen = false
    ): string {
        if (!self::hasBlockItems($content)) {
            return $content;
        }

        $schemaItems = [];
        $content = self::extractSchemaMarkers($content, $schemaItems);
        $openFirstItem = $loadOpen;
        $sequencePattern = '~(?:\s*' . preg_quote(self::ITEM_START, '~')
            . '.*?' . preg_quote(self::ITEM_END, '~') . '\s*)+~s';

        $rendered = preg_replace_callback(
            $sequencePattern,
            static function (array $matches) use ($headingLevel, $expandAll, &$openFirstItem): string {
                $itemPattern = '~' . preg_quote(self::ITEM_START, '~')
                    . '(.*?)' . preg_quote(self::ITEM_END, '~') . '~s';
                preg_match_all($itemPattern, $matches[0], $items);
                $serializedItems = implode('', $items[1] ?? []);

                if ($serializedItems === '') {
                    return '';
                }

                $backend = str_contains($serializedItems, '<!-- wp:rrze-elements/collapse')
                    ? self::BACKEND_ELEMENTS
                    : self::BACKEND_CORE;
                $group = self::renderBlockCollection(
                    $backend,
                    $serializedItems,
                    $headingLevel,
                    $expandAll,
                    $openFirstItem
                );
                $openFirstItem = false;

                return $group;
            },
            $content
        );

        if (!is_string($rendered)) {
            $rendered = $content;
        }

        return self::renderSchema($type, $schemaItems) . $rendered;
    }

    public static function hasBlockItems(string $content): bool
    {
        return str_contains($content, self::ITEM_START);
    }

    private static function isBackendAvailable(
        string $backend,
        \WP_Block_Type_Registry $registry
    ): bool {
        if ($backend === self::BACKEND_NATIVE) {
            return true;
        }

        if ($backend === self::BACKEND_ELEMENTS) {
            return $registry->is_registered('rrze-elements/collapsibles')
                && $registry->is_registered('rrze-elements/collapse');
        }

        if ($backend === self::BACKEND_CORE) {
            return $registry->is_registered('core/accordion')
                && $registry->is_registered('core/accordion-item')
                && $registry->is_registered('core/accordion-heading')
                && $registry->is_registered('core/accordion-panel');
        }

        return false;
    }

    private static function serializeElementsItem(
        string $anchor,
        string $question,
        string $answer,
        string $color,
        int $headingLevel
    ): string {
        $answerBlock = self::createStaticBlock('core/html', [], $answer);
        $item = self::createContainerBlock(
            'rrze-elements/collapse',
            [
                'title' => wp_strip_all_tags($question),
                'jumpName' => $anchor,
                'color' => $color,
                'hstart' => self::normalizeHeadingLevel($headingLevel),
                'loadOpen' => false,
                'className' => 'rrze-answers-block-item',
            ],
            [$answerBlock]
        );

        return serialize_block($item);
    }

    private static function serializeCoreItem(
        string $anchor,
        string $question,
        string $answer,
        int $headingLevel
    ): string {
        $level = self::normalizeHeadingLevel($headingLevel);
        $title = esc_html(wp_strip_all_tags($question));
        $anchorMarkup = $anchor !== ''
            ? '<span id="' . esc_attr($anchor) . '" class="rrze-answers-anchor" aria-hidden="true"></span>'
            : '';
        $answerBlock = self::createStaticBlock('core/html', [], $anchorMarkup . $answer);
        $headingMarkup = '<h' . $level . ' class="wp-block-accordion-heading has-icon has-icon-right">'
            . '<button type="button" class="wp-block-accordion-heading__toggle">'
            . '<span class="wp-block-accordion-heading__toggle-title">' . $title . '</span>'
            . '<span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span>'
            . '</button></h' . $level . '>';
        $headingBlock = self::createStaticBlock(
            'core/accordion-heading',
            [
                'title' => wp_strip_all_tags($question),
                'level' => $level,
                'iconPosition' => 'right',
                'showIcon' => true,
            ],
            $headingMarkup
        );
        $panelBlock = self::createContainerBlock(
            'core/accordion-panel',
            [],
            [$answerBlock],
            '<div class="wp-block-accordion-panel" role="region">',
            '</div>'
        );
        $itemBlock = self::createContainerBlock(
            'core/accordion-item',
            [
                'openByDefault' => false,
                'className' => 'rrze-answers-block-item',
            ],
            [$headingBlock, $panelBlock],
            '<div class="wp-block-accordion-item rrze-answers-block-item">',
            '</div>'
        );

        return serialize_block($itemBlock);
    }

    private static function renderBlockCollection(
        string $backend,
        string $serializedItems,
        int $headingLevel,
        bool $expandAll,
        bool $loadOpen
    ): string {
        if ($backend === self::BACKEND_ELEMENTS) {
            $attributes = [
                'expandAllLink' => $expandAll,
                'expandLabel' => __('Expand All', 'rrze-elements-blocks'),
                'hstart' => self::normalizeHeadingLevel($headingLevel),
            ];
            $openingMarkup = '';
            $closingMarkup = '';
            $blockName = 'rrze-elements/collapsibles';
            $itemName = 'rrze-elements/collapse';
            $openAttribute = 'loadOpen';
        } else {
            $attributes = [
                'autoclose' => true,
                'headingLevel' => self::normalizeHeadingLevel($headingLevel),
                'iconPosition' => 'right',
                'showIcon' => true,
            ];
            $openingMarkup = '<div class="wp-block-accordion" role="group">';
            $closingMarkup = '</div>';
            $blockName = 'core/accordion';
            $itemName = 'core/accordion-item';
            $openAttribute = 'openByDefault';
        }

        $parsedItems = parse_blocks($serializedItems);
        $collection = self::createContainerBlock(
            $blockName,
            $attributes,
            $parsedItems,
            $openingMarkup,
            $closingMarkup
        );
        $blocks = [$collection];

        if ($loadOpen) {
            self::openFirstBlock($blocks, $itemName, $openAttribute);
        }

        return do_blocks(serialize_blocks($blocks));
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    private static function openFirstBlock(
        array &$blocks,
        string $blockName,
        string $attribute
    ): bool {
        foreach ($blocks as &$block) {
            if (($block['blockName'] ?? null) === $blockName) {
                $block['attrs'][$attribute] = true;
                return true;
            }

            if (
                !empty($block['innerBlocks'])
                && is_array($block['innerBlocks'])
                && self::openFirstBlock($block['innerBlocks'], $blockName, $attribute)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private static function createStaticBlock(
        string $blockName,
        array $attributes,
        string $markup
    ): array {
        return [
            'blockName' => $blockName,
            'attrs' => $attributes,
            'innerBlocks' => [],
            'innerHTML' => $markup,
            'innerContent' => [$markup],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, array<string, mixed>> $innerBlocks
     * @return array<string, mixed>
     */
    private static function createContainerBlock(
        string $blockName,
        array $attributes,
        array $innerBlocks,
        string $openingMarkup = '',
        string $closingMarkup = ''
    ): array {
        $innerContent = [];
        if ($openingMarkup !== '') {
            $innerContent[] = $openingMarkup;
        }
        foreach ($innerBlocks as $_block) {
            $innerContent[] = null;
        }
        if ($closingMarkup !== '') {
            $innerContent[] = $closingMarkup;
        }

        return [
            'blockName' => $blockName,
            'attrs' => $attributes,
            'innerBlocks' => $innerBlocks,
            'innerHTML' => $openingMarkup . $closingMarkup,
            'innerContent' => $innerContent,
        ];
    }

    private static function createSchemaMarker(
        string $type,
        string $question,
        string $answer
    ): string {
        $payload = wp_json_encode([
            'type' => strtolower($type) === 'glossary' ? 'glossary' : 'faq',
            'name' => wp_strip_all_tags($question),
            'text' => trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($answer)) ?? ''),
        ]);

        if (!is_string($payload)) {
            return '';
        }

        return self::SCHEMA_PREFIX . base64_encode($payload) . ' -->';
    }

    /**
     * @param array<int, array{type: string, name: string, text: string}> $schemaItems
     */
    private static function extractSchemaMarkers(string $content, array &$schemaItems): string
    {
        $pattern = '~' . preg_quote(self::SCHEMA_PREFIX, '~') . '([A-Za-z0-9+/=]+) -->~';
        $cleaned = preg_replace_callback(
            $pattern,
            static function (array $matches) use (&$schemaItems): string {
                $decoded = base64_decode($matches[1], true);
                if (!is_string($decoded)) {
                    return '';
                }

                $item = json_decode($decoded, true);
                if (
                    is_array($item)
                    && isset($item['type'], $item['name'], $item['text'])
                    && is_string($item['type'])
                    && is_string($item['name'])
                    && is_string($item['text'])
                ) {
                    $schemaItems[] = $item;
                }

                return '';
            },
            $content
        );

        return is_string($cleaned) ? $cleaned : $content;
    }

    /**
     * @param array<int, array{type: string, name: string, text: string}> $items
     */
    private static function renderSchema(string $type, array $items): string
    {
        if ($items === []) {
            return '';
        }

        if (strtolower($type) === 'glossary') {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'DefinedTermSet',
                'hasDefinedTerm' => array_map(
                    static fn (array $item): array => [
                        '@type' => 'DefinedTerm',
                        'name' => $item['name'],
                        'description' => $item['text'],
                    ],
                    $items
                ),
            ];
        } else {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(
                    static fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['name'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['text'],
                        ],
                    ],
                    $items
                ),
            ];
        }

        $json = wp_json_encode($schema);
        if (!is_string($json)) {
            return '';
        }

        return '<script type="application/ld+json" class="rrze-answers-schema">'
            . $json
            . '</script>';
    }

    private static function renderNativeItem(
        string $type,
        string $anchor,
        string $question,
        string $answer,
        string $color,
        bool $useSchema
    ): string {
        $isGlossary = strtolower($type) === 'glossary';
        $out = '';

        if ($useSchema) {
            $out .= $isGlossary
                ? '<div itemscope itemtype="https://schema.org/DefinedTerm">'
                : '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">';
        }

        $out .= '<details id="' . esc_attr($anchor) . '" class="rrze-answers-item is-'
            . esc_attr($color) . '">';

        if (!$useSchema) {
            $out .= '<summary>' . esc_html($question) . '</summary>';
            $out .= '<div class="answers-content">' . $answer . '</div>';
        } elseif ($isGlossary) {
            $out .= '<summary itemscope itemprop="name" itemtype="https://schema.org/name">'
                . '<span itemprop="name">' . esc_html($question) . '</span></summary>';
            $out .= '<div itemscope itemprop="description" itemtype="https://schema.org/description">'
                . '<div class="rrze-answers-content" itemprop="text">' . $answer . '</div></div>';
        } else {
            $out .= '<summary itemprop="name">' . esc_html($question) . '</summary>';
            $out .= '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">'
                . '<div class="rrze-answers-content" itemprop="text">' . $answer . '</div></div>';
        }

        $out .= '</details>';

        return $useSchema ? $out . '</div>' : $out;
    }

    private static function normalizeHeadingLevel(int $headingLevel): int
    {
        return max(1, min(6, $headingLevel));
    }
}
