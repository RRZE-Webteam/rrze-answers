<?php

namespace RRZE\Answers\CPT;

defined('ABSPATH') || exit;
use RRZE\Answers\Config;

class FAQ
{

    private $lang = '';
    

    public function __construct()
    {
        $this->lang = substr(get_locale(), 0, 2);

        add_action('init', [$this, 'registerFaq'], 0);
        add_action('init', [$this, 'registerFaqTaxonomy'], 0);

        add_action('publish_rrze_faq', [$this, 'setPostMeta'], 10, 1);
        add_action('create_rrze_faq_category', [$this, 'setTermMeta'], 10, 1);
        add_action('create_rrze_faq_tag', [$this, 'setTermMeta'], 10, 1);

        add_action('rrze_faq_category_add_form_fields', [$this, 'add_category_page_field'], 10, 1);
        add_action('rrze_faq_category_edit_form_fields', [$this, 'edit_category_page_field'], 10, 1);
        add_action('created_rrze_faq_category', [$this, 'save_category_page_field'], 10, 1);
        add_action('edited_rrze_faq_category', [$this, 'save_category_page_field'], 10, 1);

        add_filter('single_template', [$this, 'filter_single_template']);
        add_filter('archive_template', [$this, 'filter_archive_template']);
        add_filter('taxonomy_template', [$this, 'filter_taxonomy_template']);
    }


    public function registerFaq()
    {
        $labels = array(
            'name' => _x('FAQ', 'FAQ, synonym or glossary entries', 'rrze-answers'),
            'singular_name' => _x('FAQ', 'Single FAQ, synonym or glossary ', 'rrze-answers'),
            'menu_name' => __('FAQ', 'rrze-answers'),
            'add_new' => __('Add FAQ', 'rrze-answers'),
            'add_new_item' => __('Add new FAQ', 'rrze-answers'),
            'edit_item' => __('Edit FAQ', 'rrze-answers'),
            'all_items' => __('All FAQ', 'rrze-answers'),
            'search_items' => __('Search FAQ', 'rrze-answers'),
        );

        // Get the slug from the options; fallback to 'faq' if not set.
        $options = get_option('rrze-answers');
        $slug = !empty($options['website_custom_faq_slug']) ? sanitize_title($options['website_custom_faq_slug']) : 'faq';

        $rewrite = array(
            'slug' => $slug, // dynamic slug
            'with_front' => true,
            'pages' => true,
            'feeds' => true,
        );
        $args = array(
            'label' => __('FAQ', 'rrze-answers'),
            'description' => __('FAQ informations', 'rrze-answers'),
            'labels' => $labels,
            'supports' => array('title', 'editor'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => true,
            'menu_icon' => 'dashicons-editor-help',
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'query_var' => 'faq',
            'rewrite' => $rewrite,
            'show_in_rest' => true,
            'rest_base' => 'faq',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        register_post_type('rrze_faq', $args);
    }

    public function registerFaqTaxonomy()
    {

        // Get the slug from the options; fallback to 'faq_category' and 'faq_tag' if not set.
        $options = get_option('rrze-answers');
        $slug_category = !empty($options['website_custom_faq_category_slug']) ? sanitize_title($options['website_custom_faq_category_slug']) : 'faq_category';
        $slug_tag = !empty($options['website_custom_faq_tag_slug']) ? sanitize_title($options['website_custom_faq_tag_slug']) : 'faq_tag';

        $tax = [
            [
                'name' => 'rrze_faq_category',
                'label' => 'FAQ ' . __('Categories', 'rrze-answers'),
                'slug' => $slug_category, // Dynamic slug
                'rest_base' => 'rrze_faq_category',
                'hierarchical' => TRUE,
                'labels' => array(
                    'singular_name' => __('Category', 'rrze-answers'),
                    'add_new' => __('Add new category', 'rrze-answers'),
                    'add_new_item' => __('Add new category', 'rrze-answers'),
                    'new_item' => __('New category', 'rrze-answers'),
                    'view_item' => __('Show category', 'rrze-answers'),
                    'view_items' => __('Show categories', 'rrze-answers'),
                    'search_items' => __('Search categories', 'rrze-answers'),
                    'not_found' => __('No category found', 'rrze-answers'),
                    'all_items' => __('All categories', 'rrze-answers'),
                    'separate_items_with_commas' => __('Separate categories with commas', 'rrze-answers'),
                    'choose_from_most_used' => __('Choose from the most used categories', 'rrze-answers'),
                    'edit_item' => __('Edit category', 'rrze-answers'),
                    'update_item' => __('Update category', 'rrze-answers')
                )
            ],
            [
                'name' => 'rrze_faq_tag',
                'label' => 'FAQ ' . __('Tags', 'rrze-answers'),
                'slug' => $slug_tag, // dynamic slug
                'rest_base' => 'rrze_faq_tag',
                'hierarchical' => FALSE,
                'labels' => array(
                    'singular_name' => __('Tag', 'rrze-answers'),
                    'add_new' => __('Add new tag', 'rrze-answers'),
                    'add_new_item' => __('Add new tag', 'rrze-answers'),
                    'new_item' => __('New tag', 'rrze-answers'),
                    'view_item' => __('Show tag', 'rrze-answers'),
                    'view_items' => __('Show tags', 'rrze-answers'),
                    'search_items' => __('Search tags', 'rrze-answers'),
                    'not_found' => __('No tag found', 'rrze-answers'),
                    'all_items' => __('All tags', 'rrze-answers'),
                    'separate_items_with_commas' => __('Separate tags with commas', 'rrze-answers'),
                    'choose_from_most_used' => __('Choose from the most used tags', 'rrze-answers'),
                    'edit_item' => __('Edit tag', 'rrze-answers'),
                    'update_item' => __('Update tag', 'rrze-answers')
                )
            ],
        ];

        foreach ($tax as $t) {
            $ret = register_taxonomy(
                $t['name'],
                'rrze_faq',
                array(
                    'hierarchical' => $t['hierarchical'],
                    'label' => $t['label'],
                    'labels' => $t['labels'],
                    'show_ui' => TRUE,
                    'show_admin_column' => TRUE,
                    'query_var' => TRUE,
                    'rewrite' => array(
                        'slug' => $t['slug'], // base slug that will display before each term
                        'with_front' => TRUE
                    ),
                    'show_in_rest' => TRUE,
                    'rest_base' => $t['rest_base'],
                    'rest_controller_class' => 'WP_REST_Terms_Controller'
                )
            );
            register_term_meta(
                $t['name'],
                'source',
                [
                    'query_var' => TRUE,
                    'type' => 'string',
                    'single' => TRUE,
                    'show_in_rest' => TRUE,
                    'rest_base' => 'source',
                    'rest_controller_class' => 'WP_REST_Terms_Controller'
                ]
            );
            register_term_meta(
                $t['name'],
                'lang',
                [
                    'query_var' => TRUE,
                    'type' => 'string',
                    'single' => TRUE,
                    'show_in_rest' => TRUE,
                    'rest_base' => 'lang',
                    'rest_controller_class' => 'WP_REST_Terms_Controller'
                ]
            );
            register_term_meta(
                $t['name'],
                'linked_page',
                [
                    'type' => 'integer',
                    'single' => TRUE,
                    'show_in_rest' => FALSE
                ]
            );

        }
    }

    public function setPostMeta($postID)
    {
        add_post_meta($postID, 'source', 'website', TRUE);
        add_post_meta($postID, 'lang', $this->lang, TRUE);
        add_post_meta($postID, 'remoteID', $postID, TRUE);
        $remoteChanged = get_post_timestamp($postID, 'modified');
        add_post_meta($postID, 'remoteChanged', $remoteChanged, TRUE);
    }

    public function setTermMeta($termID)
    {
        add_term_meta($termID, 'source', 'website', TRUE);
        add_term_meta($termID, 'lang', $this->lang, TRUE);
    }



    public static function add_category_page_field($taxonomy)
    {
        $pages = get_pages();
        echo '<div class="form-field term-linked-page-wrap">';
        echo '<label for="linked_page">' . esc_html__('Linked Page', 'rrze-answers') . '</label>';
        echo '<select name="linked_page">';
        echo '<option value="">' . esc_html__('None', 'rrze-answers') . '</option>';
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr($page->ID) . '">' . esc_html($page->post_title) . '</option>';
        }
        echo '</select></div>';
    }

    public static function edit_category_page_field($term)
    {
        wp_nonce_field('save_faq_category_meta', 'faq_category_meta_nonce');

        $pages = get_pages();
        $selected = get_term_meta($term->term_id, 'linked_page', true);

        echo '<tr class="form-field term-linked-page-wrap">';
        echo '<th><label for="linked_page">' . esc_html__('Verlinkte Seite', 'rrze-answers') . '</label></th>';
        echo '<td><select name="linked_page">';
        echo '<option value="">' . esc_html__('None', 'rrze-answers') . '</option>';
        foreach ($pages as $page) {
            $id = (int) $page->ID;

            printf(
                '<option value="%1$d" %2$s>%3$s</option>',
                esc_attr($id),
                selected($selected, $id, false),
                esc_html($page->post_title)
            );
        }
        echo '</select></td></tr>';
    }

    public static function save_category_page_field($term_id)
    {
        if (
            !isset($_POST['faq_category_meta_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['faq_category_meta_nonce'])), 'save_faq_category_meta')
        ) {
            return;
        }

        if (isset($_POST['linked_page'])) {
            update_term_meta($term_id, 'linked_page', (int) $_POST['linked_page']);
        }
    }

    public function filter_single_template($template)
    {
        global $post;
        if ('rrze_faq' === $post->post_type) {
            $template = plugin_dir_path(__DIR__) . 'templates/single-faq.php';
        }
        return $template;
    }



    public function filter_archive_template($template)
    {
    // echo '<pre>';
    // var_dump('rrze_faq');
    // exit;
        if (is_post_type_archive('rrze_faq')) {
            $template = plugin_dir_path(__DIR__) . 'templates/archive-faq.php';
        }
        return $template;
    }


    public function filter_taxonomy_template($template)
    {
        if (is_tax('rrze_faq_category')) {
            $template = plugin_dir_path(__DIR__) . 'templates/faq_category.php';
        } elseif (is_tax('rrze_faq_tag')) {
            $template = plugin_dir_path(__DIR__) . 'templates/faq_tag.php';
        }
        return $template;
    }
}