<?php

namespace RRZE\ANSWERS;

defined('ABSPATH') || exit;

use WP_Query;

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


    public static function getThemeColor(string $color)
    {
        if (!$color) {
            return '';
        }

        return 'has-' . $color . '-background-color has-' . $color . '-border-color has-contrast-color';
    }

    /**
     * Renders a single FAQ entry in an accordion (<details>/<summary>) format.
     * 
     * Optionally wraps the output in Schema.org FAQPage microdata if $useSchema is true.
     * The markup remains fully accessible and keeps the existing HTML structure intact.
     * 
     * @param string $anchor      HTML ID for the <details> element.
     * @param string $question    The FAQ question text.
     * @param string $answer      The FAQ answer HTML content.
     * @param string $color       Optional color class suffix for styling.
     * @param string $load_open   If non-empty, sets the <details> element to be open by default.
     * @param bool   $useSchema   Whether to output Schema.org Question/Answer markup.
     * @return string             The complete HTML string for the FAQ item.
     */
    public static function renderFAQItemAccordion(string $anchor, string $question, string $answer, string $color, string $load_open, bool $useSchema): string
    {
        $themeColor = self::getThemeColor($color);
        $out = $useSchema ? '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">' : '';
        $out .= '<details' . ($load_open ? ' open' : '') . ' id="' . esc_attr($anchor) . '" class="faq-item' . ($themeColor ? ' ' . esc_attr($themeColor) : '') . '">';

        if ($useSchema) {
            $out .= '<summary itemprop="name">' . esc_html($question) . '</summary>';
            $out .= '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">';
            $out .= '<div class="faq-content" itemprop="text">' . $answer . '</div>';
            $out .= '</div>'; // acceptedAnswer
        } else {
            $out .= '<summary>' . esc_html($question) . '</summary>';
            $out .= '<div class="faq-content">' . $answer . '</div>';
        }

        $out .= '</details>';
        $out .= $useSchema ? '</div>' : '';

        return $out;
    }

    /**
     * Renders a single FAQ entry as a heading and answer block (non-accordion format).
     * 
     * If $useSchema is true, wraps the output in Schema.org Question/Answer microdata.
     * This format is intended for simple lists without collapsible behavior.
     * 
     * @param string $question  The FAQ question text.
     * @param string $answer    The FAQ answer HTML content.
     * @param int    $hstart    The heading level (1–6) for the question.
     * @param bool   $useSchema Whether to output Schema.org Question/Answer markup.
     * @return string           The complete HTML string for the FAQ item.
     */
    public static function renderFAQItem(string $question, string $answer, int $hstart, bool $useSchema): string
    {
        if ($useSchema) {
            return '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">'
                . '<h' . $hstart . ' itemprop="name">' . esc_html($question) . '</h' . $hstart . '>'
                . '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer"><div itemprop="text">' . $answer . '</div></div>'
                . '</div>';
        }

        return '<h' . $hstart . '>' . esc_html($question) . '</h' . $hstart . '>' . $answer;
    }



    public static function renderFAQWrapper(?int $postID = null, string &$content, string &$headerID, bool &$masonry, string &$color, string &$additional_class, bool &$bSchema): string
    {
        $classes = 'rrze-faq';

        if ($masonry) {
            $classes .= ' faq-masonry';
        }

        if (!empty($color)) {
            $classes .= ' ' . trim($color);
        }

        if (!empty($additional_class)) {
            $classes .= ' ' . trim($additional_class);
        }

        return '<div ' . ($bSchema ? 'itemscope itemtype="https://schema.org/FAQPage" ' : '') . 'class="' . esc_attr($classes) . '" aria-labelledby="' . esc_attr($headerID) . '">' . $content . '</div>';
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
                    'include_children' => false
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

    public static function getTaxBySource($input)
    {
        $result = [];

        if (empty($input)) {
            return $result;
        }

        $categories = preg_split('/\s*,\s*/', $input);

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
}
