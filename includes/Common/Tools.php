<?php

namespace RRZE\Answers\Common;

use RRZE\Answers\Defaults;

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

    /**
     * Renders a single entry in an accordion (<details>/<summary>) format.
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

    public static function renderItemAccordion(
        string $type,
        string $anchor,
        string $question,
        string $answer,
        string $color,
        string $load_open,
        bool $useSchema
    ): string {
        // Normalize type
        $type = strtolower($type);
        $isFaq = ($type === 'faq');
        $isGlossary = ($type === 'glossary');

        $out = '';

        // Wrapper with schema depending on type
        if ($useSchema) {
            if ($isFaq) {
                $out .= '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">';
            } elseif ($isGlossary) {
                $out .= '<div itemscope itemtype="https://schema.org/DefinedTerm">';
            } else {
                // Fallback: behave like FAQ
                $out .= '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">';
            }
        }

        $out .= '<details'
            . ($load_open ? ' open' : '')
            . ' id="' . esc_attr($anchor) . '"'
            . ' class="rrze-answers-item is-' . esc_attr($color) . '">';

        if ($useSchema) {
            if ($isFaq) {
                // FAQ schema: Question + acceptedAnswer/Answer/text
                $out .= '<summary itemprop="name">' . esc_html($question) . '</summary>';
                $out .= '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">';
                $out .= '<div class="rrze-answers-content" itemprop="text">' . $answer . '</div>';
                $out .= '</div>';
            } elseif ($isGlossary) {
                // Glossary schema: DefinedTerm / name / description / text
                // Based on the structure of the former constants, but visible (no display:none)
                $out .= '<summary itemscope itemprop="name" itemtype="https://schema.org/name">'
                    . '<span itemprop="name">' . esc_html($question) . '</span>'
                    . '</summary>';

                $out .= '<div itemscope itemprop="description" itemtype="https://schema.org/description">';
                $out .= '<div class="rrze-answers-content" itemprop="text">' . $answer . '</div>';
                $out .= '</div>';
            }
        } else {
            // No schema at all
            $out .= '<summary>' . esc_html($question) . '</summary>';
            $out .= '<div class="answers-content">' . $answer . '</div>';
        }

        $out .= '</details>';

        if ($useSchema) {
            $out .= '</div>'; // close Question or DefinedTerm wrapper
        }

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
    public static function renderItem(
        string $type,
        string $question,
        string $answer,
        int $hstart,
        bool $useSchema,
        bool $hide_title
    ): string {

        $type = strtolower($type);
        $isFaq = ($type === 'faq');
        $isGlossary = ($type === 'glossary');

        // No schema
        if (!$useSchema) {
            return '<h' . $hstart . '>'
                . esc_html($question)
                . '</h' . $hstart . '>'
                . $answer;
        }

        if ($isFaq) {
            $title = $hide_title
                ? ''
                : '<h' . $hstart . ' itemprop="name">' . esc_html($question) . '</h' . $hstart . '>';

            return
                '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">'
                . $title
                . '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">'
                . '<div itemprop="text">' . $answer . '</div>'
                . '</div>'
                . '</div>';
        }

        if ($isGlossary) {
            // title may be hidden — but must keep schema structure intact
            $title = $hide_title
                ? ''
                : '<h' . $hstart . ' itemscope itemprop="name" itemtype="https://schema.org/name">'
                . '<span itemprop="name">' . esc_html($question) . '</span>'
                . '</h' . $hstart . '>';

            return
                '<div itemscope itemtype="https://schema.org/DefinedTerm">'
                . $title
                . '<div itemscope itemprop="description" itemtype="https://schema.org/description">'
                . '<div itemprop="text">' . $answer . '</div>'
                . '</div>'
                . '</div>';
        }
    }


    public static function renderWrapper(
        string $type,
        string &$content,
        string &$headerID,
        bool &$masonry,
        string &$color,
        string &$additional_class,
        bool &$bSchema,
        ?int $postID = null
    ): string {
        $isFaq = ($type === 'faq');
        $isGlossary = ($type === 'glossary');

        $classes = 'rrze-answers';
        if ($masonry) {
            $classes .= ' answers-masonry';
        }
        if (!empty($additional_class)) {
            $classes .= ' ' . trim($additional_class);
        }

        $schemaAttr = '';
        if ($bSchema) {
            if ($isFaq) {
                $schemaAttr = ' itemscope itemtype="https://schema.org/FAQPage"';
            } elseif ($isGlossary) {
                $schemaAttr = ' itemscope itemtype="https://schema.org/DefinedTermSet"';
            }
        }

        // Fallback heading text depending on type
        $fallbackTitle = $isGlossary
            ? __('Glossary', 'rrze-answers')
            : __('FAQ', 'rrze-answers');

        $title = get_the_title($postID);
        if (empty($title)) {
            $title = $fallbackTitle;
        }

        return '<div' . $schemaAttr
            . ' class="' . esc_attr($classes) . '" role="region" aria-labelledby="' . esc_attr($headerID) . '"'
            . ' data-accordion="single"'
            . ' data-scroll-offset="96"'
            . '>'
            . '<h2 id="' . esc_attr($headerID) . '" class="screen-reader-text">' . esc_html($title) . '</h2>'
            . $content
            . '</div>';
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
        $ret = '<ul class="letters list-icons">';

        foreach (range('A', 'Z') as $a) {
            if (array_key_exists($a, $aSearch)) {
                $ret .= '<li class="filled"><a href="#letter-' . $a . '">' . $a . '</a></li>';
            } else {
                $ret .= '<li aria-hidden="true" role="presentation"><span>' . $a . '</span></li>';
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
        $aCounts = [];
        foreach ($aTerms as $name => $aDetails) {
            $aCounts[$aDetails['ID']] = count($aPostIDs[$aDetails['ID']]);
        }
        $iMax = max($aCounts);
        $aSizes = [];
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
        $ret = [];

        foreach ($aTax as $taxfield => $aEntries) {
            $term_queries = [];
            $sources = [];

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

    public function hasSync($post_type): bool
    {
        $query = new WP_Query([
            'post_type' => $post_type,
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


    public static function getSitesForSelect(): array
    {
        if (!is_multisite()) {
            return [];
        }

        $pluginFile = 'rrze-answers/rrze-answers.php';
        $sites = get_sites(['public' => 1, 'archived' => 0, 'deleted' => 0]);
        $current_blog_id = get_current_blog_id();
        $result = ['' => __('-- Choose a website --', 'rrze-answers')];

        foreach ($sites as $site) {
            $blog_id = (int) $site->blog_id;

            // Skip current site by blog ID
            if ($blog_id === $current_blog_id) {
                continue;
            }

            // Only include sites where the plugin is active
            $active_plugins = get_blog_option($blog_id, 'active_plugins', []);
            if (!in_array($pluginFile, $active_plugins, true)) {
                continue;
            }

            $home_url = get_home_url($blog_id);
            $identifier = self::getIdentifier($home_url);

            $site_name = get_blog_option($blog_id, 'blogname');

            $site_url = trailingslashit(get_home_url($blog_id));

            $result[$site_url] = $identifier . ' (' . $site_name . ')';
        }

        return $result;
    }

    public static function getIdentifier($url)
    {
        $host = self::getHost($url);
        $host = preg_replace('/^www\./i', '', $host); // remove www.

        // Use the first domain segment (e.g., "phil" from phil.fau.eu)
        $identifier = explode('.', $host)[0];

        // Fallback: if no subdomain (e.g., fau.eu) use the second-to-last segment
        if (empty($identifier) || $identifier === $host) {
            $parts = explode('.', $host);
            $identifier = $parts[count($parts) - 2] ?? $host;
        }

        return $identifier;

    }


    public static function getHost($url)
    {
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        return wp_parse_url($url, PHP_URL_HOST);

    }

    public static function getPronunciation($post_id)
    {
        // returns the language in which the long form is pronounced 
        $defaults = new Defaults();
        $langlist = $defaults->get('lang');

        $lang = get_post_meta($post_id, 'titleLang', TRUE);
        return ($lang == substr(get_locale(), 0, 2) ? '' : ' (' . __('Pronunciation', 'rrze-answers') . ': ' . $langlist[$lang] . ')');
    }


    public static function logIt(string $msg): void
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $msg = wp_date("Y-m-d H:i:s") . ' | ' . $msg;

        if ($wp_filesystem->exists(RRZEANSWERSLOGFILE)) {
            $content = $wp_filesystem->get_contents(RRZEANSWERSLOGFILE);
            $content = $msg . "\n" . $content;
        } else {
            $content = $msg;
        }

        $wp_filesystem->put_contents(RRZEANSWERSLOGFILE, $content, FS_CHMOD_FILE);
    }

    public static function deleteLogfile(): void
    {
        if (file_exists(RRZEANSWERSLOGFILE)) {
            wp_delete_file(RRZEANSWERSLOGFILE);
        }
    }


    /**
     * Render callback for the FAQ block.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public static function render_faq_block($attributes)
    {
        // Define default attribute values to avoid undefined indexes.
        $defaults = [
            'id' => 0,
            'catID' => 0,
            'start' => '',
            'end' => '',
            'display' => 1,
        ];

        $attributes = wp_parse_args($attributes, $defaults);

        $id = (int) $attributes['id'];
        $cat_id = (int) $attributes['catID'];
        $start = !empty($attributes['start']) ? wp_date('Y-m-d', strtotime($attributes['start'])) : '';
        $end = !empty($attributes['end']) ? wp_date('Y-m-d', strtotime($attributes['end'])) : '';
        $display = (int) $attributes['display'];

        // Date range is optional: only limit output if values are set.
        if ($start || $end) {
            $today = wp_date('Y-m-d');

            if (($start && $today < $start) || ($end && $today > $end)) {
                // Outside of date range -> do not render anything.
                return '';
            }
        }

        // If no explicit FAQ ID is given, try to get a random one.
        // If a category is set, pick from that category; otherwise from all FAQs.
        if (!$id) {
            $id = static::get_random_faq_id($cat_id);
        }

        // If we still have no valid FAQ ID, render nothing.
        if (!$id) {
            return '';
        }

        // Map display option to shortcode attributes.
        switch ($display) {
            case 2:
                $shortcode_attr = "show='load-open'";
                break;
            case 3:
                $shortcode_attr = "hide='title'";
                break;
            case 1:
            default:
                $shortcode_attr = '';
                break;
        }

        // Build shortcode string.
        $shortcode = sprintf(
            '[faq id="%d"%s%s]',
            $id,
            $shortcode_attr ? ' ' : '',
            $shortcode_attr
        );

        // Return rendered shortcode.
        echo do_shortcode($shortcode);
    }

    /**
     * Get a random FAQ ID.
     * If a category ID is provided, pick from that category; otherwise from all FAQs.
     *
     * @param int $cat_id Category term ID (optional).
     * @return int
     */
    public static function get_random_faq_id($cat_id = 0)
    {
        $cat_id = (int) $cat_id;

        $args = [
            'posts_per_page' => -1,
            'post_type' => 'rrze_faq',
            'fields' => 'ids',
            'post_status' => 'publish',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ];

        // Add taxonomy filter only if a category is given.
        if ($cat_id) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'rrze_faq_category',
                    'field' => 'term_id',
                    'terms' => $cat_id,
                ],
            ];
        }

        $posts = get_posts($args);

        if (empty($posts)) {
            return 0;
        }

        // Pick a random ID from the list.
        $random_key = array_rand($posts);

        return (int) $posts[$random_key];
    }


}
