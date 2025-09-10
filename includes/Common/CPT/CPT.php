<?php

namespace RRZE\Answers\Common\CPT;


defined('ABSPATH') || exit;

use function RRZE\Answers\plugin;


/**
 * Base class for custom post types
 */
abstract class CPT
{
    protected $post_type = '';
    protected $lang = '';
    protected $rest_base;
    protected $menu_icon = 'dashicons-admin-post';
    protected $supports = ['title', 'editor'];
    protected $has_archive = true;
    protected $labels = [];
    protected $taxonomies = [];
    protected $templates = [];
    protected $slug_options = ['slug_option_key' => '', 'default_slug' => ''];
    protected $textdomain;

    public function __construct($posttype)
    {
        $this->post_type = $posttype;
        $this->lang = substr(get_locale(), 0, 2) ?: 'en';

        add_action('init', [$this, 'registerPostType'], 0);
        add_action('init', [$this, 'registerTaxonomies'], 0);

        add_action("publish_{$this->post_type}", [$this, 'setPostMeta'], 10, 1);
        foreach ($this->taxonomies as $tx) {
            add_action("create_{$tx['name']}", [$this, 'setTermMeta'], 10, 1);
        }

        foreach ($this->taxonomies as $tx) {
            if (!empty($tx['hierarchical'])) {
                add_action("{$tx['name']}_add_form_fields", [$this, 'add_category_page_field']);
                add_action("{$tx['name']}_edit_form_fields", [$this, 'edit_category_page_field']);
                add_action("created_{$tx['name']}", [$this, 'save_category_page_field']);
                add_action("edited_{$tx['name']}", [$this, 'save_category_page_field']);
            }
        }

        add_filter('single_template', [$this, 'filter_single_template']);
        add_filter('archive_template', [$this, 'filter_archive_template']);
        add_filter('taxonomy_template', [$this, 'filter_taxonomy_template']);
    }

    public function registerPostType()
    {
        $options = get_option('rrze-answers');
        $slug_option_key = $this->slug_options['slug_option_key'];
        $default_slug = $this->slug_options['default_slug'];
        $slug = !empty($options[$slug_option_key]) ? sanitize_title($options[$slug_option_key]) : $default_slug;

        $args = [
            'label' => $this->labels['name'] ?? __('Entries', 'rrze-answers'),
            'description' => $this->labels['name'] ?? __('Entries', 'rrze-answers'),
            'labels' => $this->labels,
            'supports' => $this->supports,
            'public' => true,
            'show_ui' => true,
            'menu_icon' => $this->menu_icon,
            'has_archive' => $this->has_archive,
            'publicly_queryable' => true,
            'query_var' => $this->rest_base,
            'rewrite' => ['slug' => $slug, 'with_front' => true],
            'show_in_rest' => true,
            'rest_base' => $this->rest_base,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ];

        register_post_type($this->post_type, $args);
    }

    public function registerTaxonomies()
    {
        $options = get_option('rrze-answers');

        foreach ($this->taxonomies as $t) {
            $slug = !empty($options[$t['slug_option_key'] ?? ''])
                ? sanitize_title($options[$t['slug_option_key']])
                : ($t['default_slug'] ?? $t['name']);

            register_taxonomy(
                $t['name'],
                $this->post_type,
                [
                    'hierarchical' => (bool) ($t['hierarchical'] ?? false),
                    'label' => $t['label'],
                    'labels' => $t['labels'] ?? [],
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'query_var' => true,
                    'rewrite' => ['slug' => $slug, 'with_front' => true],
                    'show_in_rest' => true,
                    'rest_base' => $t['rest_base'] ?? $t['name'],
                    'rest_controller_class' => 'WP_REST_Terms_Controller',
                ]
            );

            register_term_meta($t['name'], 'source', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
            register_term_meta($t['name'], 'lang', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
            register_term_meta($t['name'], 'linked_page', ['type' => 'integer', 'single' => true, 'show_in_rest' => false]);
        }
    }

    public function setPostMeta($postID)
    {
        add_post_meta($postID, 'source', 'website', true);
        add_post_meta($postID, 'lang', $this->lang, true);
        add_post_meta($postID, 'remoteID', $postID, true);
        add_post_meta($postID, 'remoteChanged', get_post_timestamp($postID, 'modified'), true);
    }

    public function setTermMeta($termID)
    {
        add_term_meta($termID, 'source', 'website', true);
        add_term_meta($termID, 'lang', $this->lang, true);
    }

    public function add_category_page_field()
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

    public function edit_category_page_field($term)
    {
        wp_nonce_field('save_term_linked_page_meta', 'term_linked_page_meta_nonce');
        $pages = get_pages();
        $selected = get_term_meta($term->term_id, 'linked_page', true);

        echo '<tr class="form-field term-linked-page-wrap">';
        echo '<th><label for="linked_page">' . esc_html__('Linked Page', 'rrze-answers') . '</label></th>';
        echo '<td><select name="linked_page">';
        echo '<option value="">' . esc_html__('None', 'rrze-answers') . '</option>';
        foreach ($pages as $page) {
            printf(
                '<option value="%1$d" %2$s>%3$s</option>',
                esc_attr($page->ID),
                selected((int) $selected, (int) $page->ID, false),
                esc_html($page->post_title)
            );
        }
        echo '</select></td></tr>';
    }

    public function save_category_page_field($term_id)
    {
        if (
            !isset($_POST['term_linked_page_meta_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['term_linked_page_meta_nonce'])), 'save_term_linked_page_meta')
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
        if ($post->post_type == $this->post_type){
            $template = plugin()->getPath() . 'templates/' . $this->templates['single'];
        }
        return $template;
    }



    public function filter_archive_template($template)
    {
        global $post;
        if ($post->post_type == $this->post_type){
            $template = plugin()->getPath() . 'templates/' . $this->templates['archive'];
        }
        return $template;
    }


    public function filter_taxonomy_template($template)
    {
        if (is_tax($this->post_type . '_category')) {
            $template = plugin()->getPath() . 'templates/' . $this->templates['taxonomy']['category'];
        } elseif (is_tax($this->post_type . '_tag')) {
            $template = plugin()->getPath() . 'templates/' . $this->templates['taxonomy']['tag'];
        }
        return $template;
    }

}
