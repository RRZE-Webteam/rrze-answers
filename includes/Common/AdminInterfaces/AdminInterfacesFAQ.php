<?php

namespace RRZE\Answers\Common\AdminInterfaces;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Config;
use RRZE\Answers\Common\API\SyncAPI\SyncAPI;
use RRZE\Answers\Common\Tools;


/**
 * Layout settings for "faq"
 */
class AdminInterfaces
{


    public function __construct()
    {

        add_filter('pre_get_posts', [$this, 'makeFaqSortable']);
        add_filter('enter_title_here', [$this, 'changeTitleText']);
        // show content in box if not editable ( not editable == source is not "website" - it is sychronized from another website )
        add_action('admin_menu', [$this, 'toggleEditor']);

        // Table "All FAQ"
        add_filter('manage_' . 'rrze_faq' . '_posts_columns', [$this, 'addFaqColumns']);
        add_action('manage_' . 'rrze_faq' . '_posts_custom_column', [$this, 'getFaqColumnsValues'], 10, 2);
        add_filter('manage_edit-' . 'rrze_faq' . '_sortable_columns', [$this, 'addFaqSortableColumns']);
        add_action('restrict_manage_posts', [$this, 'addFaqFilters'], 10, 1);
        add_filter('parse_query', [$this, 'filterRequestQuery'], 10);

        // Table "Category"
        add_filter('manage_edit-' . 'rrze_faq_category' . '_columns', [$this, 'addTaxColumns']);
        add_filter('manage_' . 'rrze_faq_category' . '_custom_column', [$this, 'getTaxColumnsValues'], 10, 3);
        add_filter('manage_edit-' . 'rrze_faq_category' . '_sortable_columns', [$this, 'addTaxColumns']);

        // Table "Tags"
        add_filter('manage_edit-' . 'rrze_faq_tag' . '_columns', [$this, 'addTaxColumns']);
        add_filter('manage_' . 'rrze_faq_tag' . '_custom_column', [$this, 'getTaxColumnsValues'], 10, 3);
        add_filter('manage_edit-' . 'rrze_faq_tag' . '_sortable_columns', [$this, 'addTaxColumns']);

        add_action('save_post_' . 'rrze_faq', [$this, 'savePostMeta']);
    }

    public function makeFaqSortable($wp_query)
    {
        if (is_admin() && !empty($wp_query->query['post_type'])) {
            $post_type = $wp_query->query['post_type'];
            if ($post_type == 'rrze_faq') {
                if (!isset($wp_query->query['orderby'])) {
                    $wp_query->set('orderby', 'title');
                    $wp_query->set('order', 'ASC');
                }

                $orderby = $wp_query->get('orderby');
                if ($orderby == 'sortfield') {
                    $wp_query->set('meta_key', 'sortfield');
                    $wp_query->set('orderby', 'meta_value');
                }
            }
        }
    }

    public function savePostMeta($postID)
    {
        if (
            !current_user_can('edit_post', $postID) ||
            !isset($_POST['sortfield'], $_POST['anchorfield'], $_POST['rrze_faq_meta_nonce']) ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rrze_faq_meta_nonce'])), 'rrze_faq_save_meta')
        ) {
            return $postID;
        }

        // Ensure slashes are unslashed and input is sanitized
        $source = (empty($_POST['source']) ? 'website' : sanitize_text_field(wp_unslash($_POST['source'])));
        update_post_meta($postID, 'source', $source);
        update_post_meta($postID, 'lang', substr(get_locale(), 0, 2));
        update_post_meta($postID, 'remoteID', $postID);
        update_post_meta($postID, 'remoteChanged', get_post_timestamp($postID, 'modified'));

        // Sanitize and unslash the input fields
        update_post_meta($postID, 'sortfield', sanitize_text_field(wp_unslash($_POST['sortfield'])));
        update_post_meta($postID, 'anchorfield', sanitize_title(wp_unslash($_POST['anchorfield'])));
    }

    public function sortboxCallback($meta_id)
    {
        wp_nonce_field('rrze_faq_save_meta', 'rrze_faq_meta_nonce');

        $output = '<input type="hidden" name="source" id="source" value="' . esc_attr(get_post_meta($meta_id->ID, 'source', true)) . '">';
        $output .= '<input type="text" name="sortfield" id="sortfield" class="sortfield" value="' . esc_attr(get_post_meta($meta_id->ID, 'sortfield', true)) . '">';
        $output .= '<p class="description">' . __('Criterion for sorting the output of the shortcode', 'rrze-answers') . '</p>';
        echo wp_kses_post($output);
    }

    public function anchorboxCallback($meta_id)
    {
        $output = '<input type="hidden" name="source" id="source" value="' . esc_attr(get_post_meta($meta_id->ID, 'source', true)) . '">';
        $output .= '<input type="text" name="anchorfield" id="anchorfield" class="anchorfield" value="' . esc_attr(get_post_meta($meta_id->ID, 'anchorfield', true)) . '">';
        $output .= '<p class="description">' . __('Anchor field (optional) to define jump marks when displayed in accordions ', 'rrze-answers') . '</p>';
        echo wp_kses_post($output);
    }


    public function langboxCallback($meta_id)
    {
        $output = '<input type="text" name="lang" id="lang" class="lang" value="' . esc_attr(get_post_meta($meta_id->ID, 'lang', true)) . '">';
        $output .= '<p class="description">' . __('Language of this FAQ', 'rrze-answers') . '</p>';
        echo wp_kses_post($output);
    }

    public function fillContentBox($post)
    {
        $mycontent = apply_filters('the_content', $post->post_content);
        echo '<h1>' . esc_html($post->post_title) . '</h1><br>' . wp_kses_post($mycontent);
    }

    public function fillShortcodeBox()
    {
        global $post;
        $ret = '';
        $category = '';
        $tag = '';
        $fields = array('rrze_faq_category', 'rrze_faq_tag');
        foreach ($fields as $field) {
            $terms = wp_get_post_terms($post->ID, $field);
            foreach ($terms as $term) {
                $$field .= $term->slug . ', ';
            }
            $$field = rtrim($$field, ', ');
        }

        if ($post->ID > 0) {
            $ret .= '<h3 class="hndle">' . __('Single entries', 'rrze-answers') . ':</h3><p>[faq id="' . $post->ID . '"]</p>';
            $ret .= ($category ? '<h3 class="hndle">' . __('Accordion with category', 'rrze-answers') . ':</h3><p>[faq category="' . $category . '"]</p><p>' . __('If there is more than one category listed, use at least one of them.', 'rrze-answers') . '</p>' : '');
            $ret .= ($tag ? '<h3 class="hndle">' . __('Accordion with tag', 'rrze-answers') . ':</h3><p>[faq tag="' . $tag . '"]</p><p>' . __('If there is more than one tag listed, use at least one of them.', 'rrze-answers') . '</p>' : '');
            $ret .= '<h3 class="hndle">' . __('Accordion with all entries', 'rrze-answers') . ':</h3><p>[faq]</p>';
        }
        echo wp_kses_post($ret);
    }

    public function changeTitleText($title)
    {
        $screen = get_current_screen();
        if ($screen->post_type == 'rrze_faq') {
            $title = __('Enter question here', 'rrze-answers');
        }
        return $title;
    }

    public function toggleEditor()
    {
        $post_id = 0;

        if (isset($_GET['post'])) {
            $post_id = isset($_GET['faq_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['faq_nonce'])), 'faq_edit_nonce') ? (int) sanitize_text_field(wp_unslash($_GET['post'])) : 0;
        } elseif (isset($_POST['post_ID'])) {
            $post_id = isset($_POST['faq_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['faq_nonce'])), 'faq_edit_nonce') ? (int) sanitize_text_field(wp_unslash($_POST['post_ID'])) : 0;
        }

        if ($post_id) {
            if (get_post_type($post_id) === 'rrze_faq') {
                $source = get_post_meta($post_id, 'source', true);
                if ($source && $source !== 'website') {
                    $api = new SyncAPI();
                    $domains = $api->getDomains();
                    $remoteID = get_post_meta($post_id, 'remoteID', true);
                    $link = esc_url($domains[$source] . 'wp-admin/post.php?post=' . $remoteID . '&action=edit');

                    remove_post_type_support('rrze_faq', 'title');
                    remove_post_type_support('rrze_faq', 'editor');
                    remove_meta_box('rrze_faq_category' . 'div', 'rrze_faq', 'side');
                    remove_meta_box('tagsdiv-' . 'rrze_faq_tag', 'rrze_faq', 'side');

                    add_meta_box(
                        'read_only_content_box',
                        sprintf(
                            '%1$s. <a href="%2$s" target="_blank">%3$s</a>',
                            esc_html__('This FAQ cannot be edited because it is synchronized', 'rrze-answers'),
                            $link,
                            esc_html__('You can edit it at the source', 'rrze-answers')
                        ),
                        [$this, 'fillContentBox'],
                        'rrze_faq',
                        'normal',
                        'high'
                    );
                }
            }

            if (!use_block_editor_for_post($post_id)) {
                add_meta_box(
                    'shortcode_box',
                    __('Integration in pages and posts', 'rrze-answers'),
                    [$this, 'fillShortcodeBox'],
                    'rrze_faq',
                    'normal'
                );
            }
        }

        add_meta_box('langbox', __('Language', 'rrze-answers'), [$this, 'langboxCallback'], 'rrze_faq', 'side');
        add_meta_box('sortbox', __('Sort', 'rrze-answers'), [$this, 'sortboxCallback'], 'rrze_faq', 'side');
        add_meta_box('anchorbox', __('Anchor', 'rrze-answers'), [$this, 'anchorboxCallback'], 'rrze_faq', 'side');
    }

    public function addFaqColumns($columns)
    {
        $columns['lang'] = __('Language', 'rrze-answers');
        $columns['sortfield'] = __('Sort criterion', 'rrze-answers');

        if ((new Tools())->hasSync()) {
            $columns['source'] = __('Source', 'rrze-answers');
        }

        return $columns;
    }

    public function addFaqSortableColumns($columns)
    {
        $columns['taxonomy-' . 'rrze_faq_category'] = __('rrze_category', 'rrze-answers');
        $columns['taxonomy-' . 'rrze_faq_tag'] = __('rrze_tag', 'rrze-answers');
        $columns['lang'] = __('Language', 'rrze-answers');
        $columns['sortfield'] = 'sortfield';

        if ((new Tools())->hasSync()) {
            $columns['source'] = __('Source', 'rrze-answers');
        }

        return $columns;
    }

    public function addFaqFilters($post_type)
    {
        if ($post_type !== 'rrze_faq') {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'bulk-posts')) {
            return;
        }

        $taxonomies_slugs = ['rrze_faq_category', 'rrze_faq_tag'];
        foreach ($taxonomies_slugs as $slug) {
            $taxonomy = get_taxonomy($slug);
            $selected = isset($_GET[$slug]) ? sanitize_text_field(wp_unslash($_GET[$slug])) : '';
            wp_dropdown_categories([
                'show_option_all' => $taxonomy->labels->all_items,
                'taxonomy' => $slug,
                'name' => $slug,
                'orderby' => 'name',
                'value_field' => 'slug',
                'selected' => $selected,
                'hierarchical' => true,
                'hide_empty' => true,
                'show_count' => true,
            ]);
        }

        $selectedVal = isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : '';

        $posts = get_posts([
            'post_type' => 'rrze_faq',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_key' => 'source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'orderby' => 'meta_value',
        ]);

        $sources = [];

        foreach ($posts as $post_id) {
            $value = get_post_meta($post_id, 'source', true);
            if (!empty($value)) {
                $sources[] = $value;
            }
        }

        $sources = array_unique($sources);
        sort($sources, SORT_NATURAL | SORT_FLAG_CASE);

        if (count($sources) < 2) {
            echo '';
        } else {
            $output = "<select name='source'>";
            $output .= '<option value="0">' . __('All Sources', 'rrze-answers') . '</option>';

            foreach ($sources as $term) {
                $selected = ($term === $selectedVal) ? 'selected' : '';
                $output .= "<option value='" . esc_attr($term) . "' $selected>" . esc_html($term) . "</option>";
            }

            $output .= "</select>";
            echo wp_kses_post($output);
        }
    }

    public function filterRequestQuery($query)
    {
        if (!(is_admin() && $query->is_main_query())) {
            return $query;
        }

        if (!isset($query->query['post_type']) || $query->query['post_type'] !== 'rrze_faq') {
            return $query;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'bulk-posts')) {
            return $query;
        }

        if (isset($_GET['source'])) {
            $source = sanitize_text_field(wp_unslash($_GET['source']));
            if (!empty($source)) {
                $query->query_vars['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    [
                        'key' => 'source', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                        'value' => $source,
                        'compare' => '=',
                    ],
                ];
            }
        }

        return $query;
    }

    public function addTaxColumns($columns)
    {
        $columns['lang'] = __('Language', 'rrze-answers');

        if ((new Tools())->hasSync()) {
            $columns['source'] = __('Source', 'rrze-answers');
        }
        return $columns;
    }

    public function getFaqColumnsValues($column_name, $post_id)
    {
        if ($column_name == 'lang') {
            echo esc_html(get_post_meta($post_id, 'lang', true));
        }
        if ($column_name == 'source') {
            if ((new Tools())->hasSync()) {
                echo esc_html(get_post_meta($post_id, 'source', true));
            }
        }
        if ($column_name == 'sortfield') {
            echo esc_html(get_post_meta($post_id, 'sortfield', true));
        }
        if ($column_name == 'anchorfield') {
            echo esc_html(get_post_meta($post_id, 'anchorfield', true));
        }
    }

    public function getTaxColumnsValues($content, $column_name, $term_id)
    {
        if ($column_name == 'lang') {
            $lang = get_term_meta($term_id, 'lang', true);
            echo esc_html($lang);
        }
        if ($column_name == 'source') {
            if ((new Tools())->hasSync()) {
                $source = get_term_meta($term_id, 'source', true);
                echo esc_html($source);
            }
        }
    }
}
