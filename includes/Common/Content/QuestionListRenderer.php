<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Content;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\AccordionRenderer;
use RRZE\Answers\Common\TabsRenderer;
use RRZE\Answers\Common\Tools;

/**
 * Shared FAQ/glossary presentation, with schema state local to each render call.
 */
final class QuestionListRenderer
{
    /** @var array<string, true> Entry and control IDs reserved during this request. */
    private static array $usedAnchorIds = [];

    /**
     * Render a question list and enqueue the assets needed by its generated markup.
     *
     * @param string $type FAQ or glossary entry type.
     * @param array<string, mixed> $attributes Normalized shortcode or block attributes.
     * @return string Complete public list markup.
     */
    public static function render(string $type, array $attributes): string
    {
        $posts = QuestionListQuery::select($type, $attributes);
        $hasSchema = false;
        $explicit = !empty($attributes['id']);
        $content = !$explicit && $attributes['grouping'] !== ''
            ? self::renderGrouped($type, $posts, $attributes, $hasSchema)
            : self::renderFlat($type, $posts, $attributes, $explicit, $hasSchema);

        if (!AccordionRenderer::hasBlockItems($content)) {
            wp_enqueue_script('rrze-answers-accordion');
        }
        wp_enqueue_style('rrze-answers-css');
        if ($attributes['search']) {
            wp_enqueue_script('rrze-answers-search');
        }
        if (str_contains($content, 'data-rrze-answers-legacy-anchor=')) {
            wp_enqueue_script('rrze-answers-index');
        }

        $postId = (int) get_the_ID();
        $headerId = (new Tools())->getHeaderID($postId);
        return Tools::renderWrapper(
            $type,
            $content,
            $headerId,
            $attributes['masonry'],
            $attributes['color'],
            $attributes['additional_class'],
            $hasSchema,
            $postId,
            $attributes['search'],
            $attributes['load_open'],
            $attributes['hstart'],
            $attributes['expand_all_link']
        );
    }

    /**
     * Render flat entries while retaining the first unique target for each letter.
     *
     * @param string $type FAQ or glossary entry type.
     * @param \WP_Post[] $posts Selected entries in query order.
     * @param array<string, mixed> $attributes Normalized display attributes.
     * @param bool $explicit Whether entries were selected by ID.
     * @param bool $hasSchema Receives whether any rendered entry includes schema data.
     * @return string Entry markup and optional alphabetical navigation.
     */
    private static function renderFlat(string $type, array $posts, array $attributes, bool $explicit, bool &$hasSchema): string
    {
        $entries = [];
        $letters = [];
        foreach ($posts as $post) {
            $title = (string) get_the_title($post->ID);
            $letter = Tools::getLetter($title);
            $letters[$letter] = true;
            $entries[] = ['id' => $post->ID, 'title' => $title, 'letter' => $letter];
        }

        $alphabetical = $attributes['index_style'] === 'a-z';
        // Filtered lists retain their requested query order; explicit A–Z lists sort by title.
        if ($explicit && $alphabetical) {
            usort(
                $entries,
                /**
                 * Compare selected titles using their accent-insensitive natural order.
                 *
                 * @param array{id: int, title: string, letter: string} $a First entry.
                 * @param array{id: int, title: string, letter: string} $b Second entry.
                 * @return int Relative title order.
                 */
                static fn (array $a, array $b): int => strnatcasecmp(remove_accents($a['title']), remove_accents($b['title']))
            );
        }

        $indexPrefix = wp_unique_id('rrze-answers-index-');
        $content = $alphabetical && ($explicit || $letters) ? Tools::createAZ($letters, $indexPrefix) : '';
        $lastLetter = '';
        $linkedLetters = [];
        foreach ($entries as $entry) {
            if ($alphabetical && $lastLetter !== $entry['letter']) {
                $content .= self::letterHeading($indexPrefix, $entry['letter'], !isset($linkedLetters[$entry['letter']]));
                $linkedLetters[$entry['letter']] = true;
            }
            $content .= self::renderEntry($type, $entry['id'], $entry['title'], $attributes, false, $hasSchema);
            $lastLetter = $entry['letter'];
        }
        return $content;
    }

    /**
     * Render taxonomy groups with legacy-link mappings inside their actual targets.
     *
     * @param string $type FAQ or glossary entry type.
     * @param \WP_Post[] $posts Selected entries in query order.
     * @param array<string, mixed> $attributes Normalized display attributes.
     * @param bool $hasSchema Receives whether any rendered entry includes schema data.
     * @return string Group sections or deferred Elements tabs.
     */
    private static function renderGrouped(string $type, array $posts, array $attributes, bool &$hasSchema): string
    {
        if (!$posts) {
            return '';
        }

        $taxonomy = 'rrze_' . $type . '_' . $attributes['grouping'];
        $validTermIds = self::selectedTermIds($taxonomy, $attributes[$attributes['grouping']]);
        $usedTerms = [];
        $postIds = [];
        $letters = [];
        foreach ($posts as $post) {
            $terms = wp_get_post_terms($post->ID, $taxonomy);
            if (is_wp_error($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $termId = (int) (is_object($term) ? ($term->term_id ?? 0) : ($term['term_id'] ?? 0));
                $termName = (string) (is_object($term) ? ($term->name ?? '') : ($term['name'] ?? ''));
                if (!$termId || $termName === '' || ($validTermIds && !in_array($termId, $validTermIds, true))) {
                    continue;
                }
                $letter = Tools::getLetter($termName);
                $letters[$letter] = true;
                $usedTerms[$termName] = ['letter' => $letter, 'ID' => $termId];
                $postIds[$termId][] = $post->ID;
            }
        }
        ksort($usedTerms);

        $indexPrefix = wp_unique_id('rrze-answers-index-');
        $termPrefix = $indexPrefix . '-term';
        $useTabs = $attributes['index_style'] === 'tabs' && TabsRenderer::isAvailable();
        $content = '';
        if ($letters) {
            if ($attributes['index_style'] === 'a-z') {
                $content = Tools::createAZ($letters, $indexPrefix);
            } elseif ($attributes['index_style'] === 'tagcloud') {
                $content = Tools::createTagCloud($usedTerms, $postIds, $termPrefix);
            }
        }

        $lastLetter = '';
        $linkedLetters = [];
        $tabItems = [];
        foreach ($usedTerms as $name => $term) {
            if ($attributes['index_style'] === 'a-z' && $lastLetter !== $term['letter']) {
                $content .= self::letterHeading($indexPrefix, $term['letter'], !isset($linkedLetters[$term['letter']]));
                $linkedLetters[$term['letter']] = true;
            }
            $targetAttributes = self::indexTargetAttributes($termPrefix . '-' . $term['ID'], 'ID-' . $term['ID']);
            $termContent = $useTabs
                ? '<div' . $targetAttributes . ' class="answers-term-content">'
                : '<section' . $targetAttributes . ' class="' . esc_attr('rrze-answers-item is-' . $attributes['color']) . '">'
                    . '<h3>' . esc_html($name) . '</h3><div class="answers-term-content">';

            foreach ($postIds[$term['ID']] as $id) {
                $termContent .= self::renderEntry($type, $id, (string) get_the_title($id), $attributes, true, $hasSchema, $term['ID']);
            }
            $termContent .= $useTabs ? '</div>' : '</div></section>';
            if ($useTabs) {
                $tabItems[] = ['title' => (string) $name, 'content' => $termContent];
            } else {
                $content .= $termContent;
            }
            $lastLetter = $term['letter'];
        }
        if ($useTabs) {
            $content .= TabsRenderer::createCollection($tabItems, $attributes['color']);
        }
        return $content;
    }

    /** @return int[] */
    private static function selectedTermIds(string $taxonomy, $selection): array
    {
        $ids = [];
        $slugs = is_array($selection) ? $selection : array_map('trim', explode(',', (string) $selection));
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term) {
                $ids[] = (int) $term->term_id;
            }
        }
        return $ids;
    }

    /**
     * Render a letter heading without duplicating targets when query order repeats it.
     *
     * @param string $prefix Unique identifier for this list.
     * @param string $letter Displayed initial character.
     * @param bool $hasTarget Whether this is the first occurrence of the letter.
     * @return string Heading markup with an optional legacy-link mapping.
     */
    private static function letterHeading(string $prefix, string $letter, bool $hasTarget = true): string
    {
        $attributes = $hasTarget ? self::indexTargetAttributes($prefix . '-' . $letter, 'letter-' . $letter) : '';
        return '<h2' . $attributes . '>' . esc_html($letter) . '</h2>';
    }

    /**
     * Map an old fragment to a unique target without claiming a potentially used ID.
     *
     * The browser resolves mappings after all lists and individual entry anchors
     * exist, giving actual IDs priority and choosing the first matching DOM target.
     *
     * @param string $id Unique target used by current index links.
     * @param string $legacyAnchor Fragment emitted by the previous plugin version.
     * @return string Escaped HTML attributes, including their leading space.
     */
    private static function indexTargetAttributes(string $id, string $legacyAnchor): string
    {
        return ' id="' . esc_attr($id) . '" data-rrze-answers-legacy-anchor="' . esc_attr($legacyAnchor) . '"';
    }

    /**
     * Render one occurrence, reserving its anchor before rendering nested content.
     *
     * @param string $type FAQ or glossary entry type.
     * @param int $id Entry post ID, shared by all its occurrences.
     * @param string $title Display title.
     * @param array<string, mixed> $attributes Normalized display attributes.
     * @param bool $grouped Whether this occurrence belongs to a taxonomy group.
     * @param bool $hasSchema Receives whether any entry includes schema data.
     * @param int $termId Current category or tag ID, or zero for flat lists.
     * @return string Entry markup with a request-wide unique accordion anchor.
     */
    private static function renderEntry(
        string $type,
        int $id,
        string $title,
        array $attributes,
        bool $grouped,
        bool &$hasSchema,
        int $termId = 0
    ): string {
        $useSchema = get_post_meta($id, 'source', true) === 'website';
        $hasSchema = $hasSchema || $useSchema;

        // Grouped lists historically always render accordion entries.
        $useAccordion = $grouped || !$attributes['hide_accordion'];
        $anchor = '';
        if ($useAccordion) {
            $fallback = ($grouped ? 'innerID-' : 'ID-') . $id;
            $anchor = self::reserveEntryAnchor(
                (string) (get_post_meta($id, 'anchorfield', true) ?: $fallback),
                $grouped ? $attributes['grouping'] . '-' . $termId : ''
            );
        }
        $answer = str_replace(']]>', ']]&gt;', apply_filters('the_content', get_post_field('post_content', $id)));

        if (!$useAccordion) {
            return Tools::renderItem($type, $title, $answer, $attributes['hstart'], $useSchema, $attributes['hide_title']);
        }
        return Tools::renderItemAccordion(
            $type,
            $anchor,
            $title,
            $answer,
            $attributes['color'],
            (string) $attributes['load_open'],
            $useSchema,
            $attributes['hstart']
        );
    }

    /**
     * Keep the first anchor and disambiguate later occurrences across all lists.
     *
     * Preserve the existing anchor spelling and reserve Elements Collapse's
     * derived panel ID too. Context uses term IDs so renaming a term
     * does not change the suffix. Repeated contexts receive a numeric suffix;
     * these identifiers are deterministic for the same request render order.
     *
     * @param string $anchor Requested custom or historical default anchor.
     * @param string $context Category/tag and term ID, or empty for flat entries.
     * @return string Available anchor, reserved until the PHP request ends.
     */
    private static function reserveEntryAnchor(string $anchor, string $context): string
    {
        $elements = AccordionRenderer::getBackend() === AccordionRenderer::BACKEND_ELEMENTS;
        $candidate = $anchor;
        $stem = $anchor . ($context !== '' ? '-' . $context : '');
        $suffix = $context !== '' ? 1 : 2;
        while (isset(self::$usedAnchorIds[$candidate]) || ($elements && isset(self::$usedAnchorIds[$candidate . '-section']))) {
            $candidate = $stem . ($suffix === 1 ? '' : '-' . $suffix);
            $suffix++;
        }

        self::$usedAnchorIds[$candidate] = true;
        if ($elements) {
            self::$usedAnchorIds[$candidate . '-section'] = true;
        }
        return $candidate;
    }
}
