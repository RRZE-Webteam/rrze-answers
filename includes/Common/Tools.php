<?php

namespace RRZE\Answers\Common;

defined('ABSPATH') || exit;

use WP_Query;
use RRZE\Answers\Common\Config;

class Tools
{


    public function __construct()
    {
    }

    public static function preventGutenbergDoubleBracketBug(string $shortcode_tag)
    {
        global $post;

        if (!($post instanceof \WP_Post) || !isset($post->post_content)) {
            return '';
        }

        if (strpos($post->post_content, '[[' . $shortcode_tag . ']]') !== false) {
            return esc_html("[[$shortcode_tag]]");
        }

        return false;
    }

    public static function sortIt(&$arr)
    {
        uasort($arr, function ($a, $b) {
            return strtolower($a) <=> strtolower($b);
        });
    }

    public static function searchArrayByKey(&$needle, &$aHaystack)
    {
        foreach ($aHaystack as $k => $v) {
            if ($k === $needle) {
                return $v;
            }
        }
        return false;
    }

    public function getHeaderID(?int $postID = null): string
    {
        $random = wp_rand();
        return 'header-' . ($postID ?? 'noid') . '-' . $random;
    }

    public static function renderFAQWrapper(?int $postID = null, string &$content, string &$headerID, bool &$masonry, string &$color, string &$additional_class): string
    {
        $classes = 'rrze-answers';

        if ($masonry) {
            $classes .= ' faq-masonry';
        }

        if (!empty($color)) {
            $classes .= ' ' . trim($color);
        }

        if (!empty($additional_class)) {
            $classes .= ' ' . trim($additional_class);
        }

        return '<div class="' . esc_attr($classes) . '" aria-labelledby="' . esc_attr($headerID) . '">' . $content . '</div>';
    }

    public static function getLetter(&$txt)
    {
        return mb_strtoupper(mb_substr(remove_accents($txt), 0, 1), 'UTF-8');
    }

    public static function createAZ(&$aSearch)
    {
        if (count($aSearch) == 1) {
            return '';
        }
        $ret = '<ul class="letters">';

        foreach (range('A', 'Z') as $a) {
            if (array_key_exists($a, $aSearch)) {
                $ret .= '<li class="filled"><a href="#letter-' . $a . '">' . $a . '</a></li>';
            } else {
                $ret .= '<li>' . $a . '</li>';
            }
        }
        return $ret . '</ul>';
    }

    public static function createTabs(&$aTerms, $aPostIDs)
    {
        if (count($aTerms) == 1) {
            return '';
        }
        $ret = '';
        foreach ($aTerms as $name => $aDetails) {
            $ret .= '<a href="#ID-' . $aDetails['ID'] . '">' . $name . '</a> | ';
        }
        return rtrim($ret, ' | ');
    }

    public static function createTagcloud(&$aTerms, $aPostIDs)
    {
        if (count($aTerms) == 1) {
            return '';
        }
        $ret = '';
        $smallest = 12;
        $largest = 22;
        $aCounts = array();
        foreach ($aTerms as $name => $aDetails) {
            $aCounts[$aDetails['ID']] = count($aPostIDs[$aDetails['ID']]);
        }
        $iMax = max($aCounts);
        $aSizes = array();
        foreach ($aCounts as $ID => $cnt) {
            $aSizes[$ID] = round(($cnt / $iMax) * $largest, 0);
            $aSizes[$ID] = ($aSizes[$ID] < $smallest ? $smallest : $aSizes[$ID]);
        }
        foreach ($aTerms as $name => $aDetails) {
            $ret .= '<a href="#ID-' . $aDetails['ID'] . '" style="font-size:' . $aSizes[$aDetails['ID']] . 'px">' . $name .
                '</a> | ';
        }
        return rtrim($ret, ' | ');
    }

    public static function getTaxQuery(&$aTax)
    {
        $ret = array();

        foreach ($aTax as $taxfield => $aEntries) {
            $term_queries = array();
            $sources = array();

            foreach ($aEntries as $entry) {
                $source = !empty($entry['source']) ? $entry['source'] : '';
                $term_queries[$source][] = $entry['value'];
            }

            foreach ($term_queries as $source => $aTerms) {

                $query = array(
                    'taxonomy' => $taxfield,
                    'field' => 'slug',
                    'terms' => $aTerms,
                );

                if (count($aTerms) > 1) {
                    $query['operator'] = 'IN';
                }

                if (!empty($source)) {
                    $query['meta_key'] = 'source';
                    $query['meta_value'] = $source;
                }

                $ret[$taxfield][] = $query;
            }
            if (count($ret[$taxfield]) > 1) {
                $ret[$taxfield]['relation'] = 'OR';
            }
        }

        if (count($ret) > 1) {
            $ret['relation'] = 'AND';
        }

        return $ret;
    }

    public static function getSchema(int &$postID, string &$question, string &$answer): string
    {
        $schema = '';
        $source = get_post_meta($postID, "source", true);
        $answer = wp_strip_all_tags($answer, true);
        $schemaHTML = Config::getConstants('schema');

        if ($source === 'website') {
            $schema = $schemaHTML['RRZE_SCHEMA_QUESTION_START'] . $question . $schemaHTML['RRZE_SCHEMA_QUESTION_END'];
            $schema .= $schemaHTML['RRZE_SCHEMA_ANSWER_START'] . $answer . $schemaHTML['RRZE_SCHEMA_ANSWER_END'];
        }
        return $schema;
    }

    public static function getTaxBySource($input)
    {
        $result = [];

        if (empty($input)) {
            return $result;
        }

        $categories = explode(', ', $input);

        foreach ($categories as $category) {
            list($source, $value) = array_pad(explode(':', $category, 2), 2, '');

            if ($value === '') {
                $value = $source;
                $source = '';
            }

            $result[] = array(
                'source' => preg_replace('/[\s,]+$/', '', $source),
                'value' => preg_replace('/[\s,]+$/', '', $value)
            );
        }

        return $result;
    }

    public function getTermLinks($postID, $mytaxonomy)
    {
        $ret = '';
        $terms = wp_get_post_terms($postID, $mytaxonomy);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        foreach ($terms as $term) {
            $link = get_term_link($term->slug, $mytaxonomy);
            if (!is_wp_error($link)) {
                $ret .= '<a href="' . esc_url($link) . '">' . esc_html($term->name) . '</a>, ';
            }
        }

        return rtrim($ret, ', ');
    }

    public function getLinkedPage(int &$postID): ?array
    {
        $assigned_terms = get_the_terms($postID, 'rrze_faq_category');
        if (!$assigned_terms || is_wp_error($assigned_terms)) {
            return null;
        }

        $parent_term_ids = array_filter(wp_list_pluck($assigned_terms, 'parent'));

        $top_level_terms = array_filter($assigned_terms, function ($term) use ($parent_term_ids) {
            return !in_array($term->term_id, $parent_term_ids, true);
        });

        foreach ($top_level_terms as $term) {
            $linked_page_id = get_term_meta($term->term_id, 'linked_page', true);
            if (!$linked_page_id || get_post_status($linked_page_id) !== 'publish') {
                continue;
            }

            return [
                'url' => get_permalink($linked_page_id),
                'title' => get_the_title($linked_page_id),
            ];
        }

        return null;
    }

    public function hasSync(): bool
    {
        $query = new WP_Query([
            'post_type' => 'rrze_faq',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'source',
                    'value' => 'website',
                    'compare' => '!=',
                ],
            ],
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        return $query->have_posts();
    }

    public static function getPageList(): array
    {
        $pages = \get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'asc',
            'post_status' => 'publish'
        ]);

        $options = ['' => __('Default archive', 'rrze-answers')];
        foreach ($pages as $page) {
            $options[get_permalink($page->ID)] = $page->post_title;
        }
        return $options;
    }

}
