<?php

declare(strict_types=1);

namespace RRZE\Answers\Common;

defined('ABSPATH') || exit;

final class TabsRenderer
{
    private const COLLECTION_START = '<!-- rrze-answers-tabs:start:';
    private const COLLECTION_END = '<!-- rrze-answers-tabs:end -->';
    private const ITEM_START = '<!-- rrze-answers-tab:start:';
    private const ITEM_END = '<!-- rrze-answers-tab:end -->';

    /**
     * Check whether RRZE Elements Blocks provides both required tab blocks.
     */
    public static function isAvailable(): bool
    {
        $registry = \WP_Block_Type_Registry::get_instance();

        return $registry->is_registered('rrze-elements/tabs')
            && $registry->is_registered('rrze-elements/tab');
    }

    /**
     * Defer rendering until nested accordion collections have been rendered.
     *
     * @param array<int, array{title: string, content: string}> $items
     */
    public static function createCollection(array $items, string $color = ''): string
    {
        if ($items === []) {
            return '';
        }

        if (!self::isAvailable()) {
            return implode('', array_column($items, 'content'));
        }

        $collectionMetadata = self::encodeMetadata(['color' => $color]);
        $content = self::COLLECTION_START . $collectionMetadata . ' -->';

        foreach ($items as $item) {
            $itemMetadata = self::encodeMetadata(['title' => $item['title']]);
            $content .= self::ITEM_START . $itemMetadata . ' -->'
                . $item['content']
                . self::ITEM_END;
        }

        return $content . self::COLLECTION_END;
    }

    public static function hasCollections(string $content): bool
    {
        return str_contains($content, self::COLLECTION_START);
    }

    /**
     * Replace deferred collections after nested block content is complete.
     */
    public static function renderCollections(string $content): string
    {
        if (!self::hasCollections($content)) {
            return $content;
        }

        $collectionPattern = '~' . preg_quote(self::COLLECTION_START, '~')
            . '([A-Za-z0-9+/=]+) -->(.*?)'
            . preg_quote(self::COLLECTION_END, '~') . '~s';

        $rendered = preg_replace_callback(
            $collectionPattern,
            static function (array $matches): string {
                $metadata = self::decodeMetadata($matches[1]);
                $itemPattern = '~' . preg_quote(self::ITEM_START, '~')
                    . '([A-Za-z0-9+/=]+) -->(.*?)'
                    . preg_quote(self::ITEM_END, '~') . '~s';
                preg_match_all($itemPattern, $matches[2], $itemMatches, PREG_SET_ORDER);

                $items = [];
                foreach ($itemMatches as $itemMatch) {
                    $itemMetadata = self::decodeMetadata($itemMatch[1]);
                    $items[] = [
                        'title' => (string) ($itemMetadata['title'] ?? ''),
                        'content' => $itemMatch[2],
                    ];
                }

                if ($items === []) {
                    return self::stripItemMarkers($matches[2]);
                }

                return self::render($items, (string) ($metadata['color'] ?? ''));
            },
            $content
        );

        return is_string($rendered) ? $rendered : $content;
    }

    /**
     * Render grouped content with the RRZE Elements Blocks tabs implementation.
     *
     * @param array<int, array{title: string, content: string}> $items
     */
    private static function render(array $items, string $color = ''): string
    {
        if ($items === []) {
            return '';
        }

        if (!self::isAvailable()) {
            return implode('', array_column($items, 'content'));
        }

        $tabsUid = wp_unique_id('rrze-answers-tabs-');
        $tabBlocks = [];
        $tabDefinitions = [];

        foreach ($items as $position => $item) {
            $clientId = wp_unique_id('ratab-');
            $title = wp_strip_all_tags($item['title']);
            $contentBlock = self::createStaticBlock(
                'core/html',
                [],
                $item['content']
            );

            $tabBlocks[] = self::createContainerBlock(
                'rrze-elements/tab',
                [
                    'title' => $title,
                    'order' => $position,
                    'active' => $position === 0,
                    'blockId' => $clientId,
                    'tabsUid' => $tabsUid,
                    'className' => 'rrze-answers-item is-' . sanitize_html_class($color),
                ],
                [$contentBlock]
            );
            $tabDefinitions[] = [
                'clientId' => $clientId,
                'title' => $title,
                'position' => $position,
                'icon' => '',
                'svgString' => '',
                'materialSymbol' => '',
            ];
        }

        $tabsBlock = self::createContainerBlock(
            'rrze-elements/tabs',
            [
                'blockId' => $tabsUid,
                'innerClientIds' => $tabDefinitions,
                'active' => $tabDefinitions[0]['clientId'],
                'color' => sanitize_html_class($color),
                'className' => 'rrze-answers-elements-tabs',
            ],
            $tabBlocks
        );

        return do_blocks(serialize_block($tabsBlock));
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
        array $innerBlocks
    ): array {
        return [
            'blockName' => $blockName,
            'attrs' => $attributes,
            'innerBlocks' => $innerBlocks,
            'innerHTML' => '',
            'innerContent' => array_fill(0, count($innerBlocks), null),
        ];
    }

    /**
     * @param array<string, string> $metadata
     */
    private static function encodeMetadata(array $metadata): string
    {
        $encoded = wp_json_encode($metadata);

        return base64_encode(is_string($encoded) ? $encoded : '{}');
    }

    /** @return array<string, mixed> */
    private static function decodeMetadata(string $encoded): array
    {
        $decoded = base64_decode($encoded, true);
        if (!is_string($decoded)) {
            return [];
        }

        $metadata = json_decode($decoded, true);

        return is_array($metadata) ? $metadata : [];
    }

    private static function stripItemMarkers(string $content): string
    {
        $startPattern = '~' . preg_quote(self::ITEM_START, '~')
            . '[A-Za-z0-9+/=]+ -->~';
        $content = preg_replace($startPattern, '', $content) ?? $content;

        return str_replace(self::ITEM_END, '', $content);
    }
}
