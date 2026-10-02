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

    /** @param \WP_Post[] $posts */
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
            usort($entries, static fn (array $a, array $b): int => strnatcasecmp(remove_accents($a['title']), remove_accents($b['title'])));
        }

        $indexPrefix = wp_unique_id('rrze-answers-index-');
        $content = $alphabetical && ($explicit || $letters) ? Tools::createAZ($letters, $indexPrefix) : '';
        $lastLetter = '';
        foreach ($entries as $entry) {
            if ($alphabetical && $lastLetter !== $entry['letter']) {
                $content .= self::letterHeading($indexPrefix, $entry['letter']);
            }
            $content .= self::renderEntry($type, $entry['id'], $entry['title'], $attributes, false, $hasSchema);
            $lastLetter = $entry['letter'];
        }
        return $content;
    }

    /** @param \WP_Post[] $posts */
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
        $tabItems = [];
        foreach ($usedTerms as $name => $term) {
            if ($attributes['index_style'] === 'a-z' && $lastLetter !== $term['letter']) {
                $content .= self::letterHeading($indexPrefix, $term['letter']);
            }
            $termContent = $useTabs
                ? '<div class="answers-term-content">'
                : '<section id="' . esc_attr($termPrefix . '-' . $term['ID']) . '" class="' . esc_attr('rrze-answers-item is-' . $attributes['color']) . '">'
                    . '<h3>' . esc_html($name) . '</h3><div class="answers-term-content">';

            foreach ($postIds[$term['ID']] as $id) {
                $termContent .= self::renderEntry($type, $id, (string) get_the_title($id), $attributes, true, $hasSchema);
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

    private static function letterHeading(string $prefix, string $letter): string
    {
        return '<h2 id="' . esc_attr($prefix . '-' . $letter) . '">' . esc_html($letter) . '</h2>';
    }

    private static function renderEntry(string $type, int $id, string $title, array $attributes, bool $grouped, bool &$hasSchema): string
    {
        $useSchema = get_post_meta($id, 'source', true) === 'website';
        $hasSchema = $hasSchema || $useSchema;
        $answer = str_replace(']]>', ']]&gt;', apply_filters('the_content', get_post_field('post_content', $id)));

        // Grouped lists historically always render accordion entries.
        if (!$grouped && $attributes['hide_accordion']) {
            return Tools::renderItem($type, $title, $answer, $attributes['hstart'], $useSchema, $attributes['hide_title']);
        }
        $anchor = get_post_meta($id, 'anchorfield', true);
        if (empty($anchor)) {
            $anchor = ($grouped ? 'innerID-' : 'ID-') . $id;
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
}
