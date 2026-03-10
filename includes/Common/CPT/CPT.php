<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;

use function RRZE\Answers\plugin;

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

    public function __construct($posttype)
    {
        $this->post_type = $posttype;
        $this->lang = substr(get_locale(), 0, 2) ?: 'en';

        add_action('init', [$this, 'registerPostType'], 0);
        add_action('init', [$this, 'registerTaxonomies'], 0);

        add_action("publish_{$this->post_type}", [$this, 'setPostMeta'], 10);

        foreach ($this->taxonomies as $tx) {

            add_action("create_{$tx['name']}", [$this, 'setTermMeta'], 10);

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

        add_action('init', [$this, 'maybeFlushRewriteRules'], 20);
        add_action('update_option_rrze-answers', [$this, 'checkSlugChange'], 10, 2);

        add_action('template_redirect', [$this, 'maybe_disable_canonical_redirect'], 1);
        add_action('template_redirect', [$this, 'custom_cpt_404_message'], 10);
    }

    /**
     * Register CPT
     */
    public function registerPostType()
    {
        $options = get_option('rrze-answers');

        $slug = !empty($options[$this->slug_options['slug_option_key']])
            ? sanitize_title($options[$this->slug_options['slug_option_key']])
            : $this->slug_options['default_slug'];

        $rewrite = [
            'slug' => $slug,
            'with_front' => true,
            'pages' => true,
            'feeds' => true,
        ];

        register_post_type($this->post_type, [
            'label' => $this->labels['name'] ?? __('Entries', 'rrze-answers'),
            'labels' => $this->labels,
            'supports' => $this->supports,
            'public' => true,
            'show_ui' => true,
            'menu_icon' => $this->menu_icon,
            'has_archive' => $this->has_archive,
            'publicly_queryable' => true,
            'query_var' => $this->rest_base,
            'rewrite' => $rewrite,
            'show_in_rest' => true,
            'rest_base' => $this->rest_base,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ]);
    }

    /**
     * Register taxonomies
     */
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

            register_term_meta($t['name'], 'source', [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true
            ]);

            register_term_meta($t['name'], 'lang', [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true
            ]);

            register_term_meta($t['name'], 'linked_page', [
                'type' => 'integer',
                'single' => true,
                'show_in_rest' => false
            ]);
        }
    }

    /**
     * Set default post meta
     */
    public function setPostMeta($postID)
    {
        add_post_meta($postID, 'source', 'website', true);
        add_post_meta($postID, 'lang', $this->lang, true);
        add_post_meta($postID, 'remoteID', $postID, true);
        add_post_meta($postID, 'remoteChanged', get_post_timestamp($postID, 'modified'), true);
    }

    /**
     * Set default term meta
     */
    public function setTermMeta($termID)
    {
        add_term_meta($termID, 'source', 'website', true);
        add_term_meta($termID, 'lang', $this->lang, true);
    }

    /**
     * Template handling
     */
    public function filter_single_template($template)
    {
        if (is_singular($this->post_type)) {
            return plugin()->getPath() . 'templates/' . $this->templates['single'];
        }
        return $template;
    }

    public function filter_archive_template($template)
    {
        if (is_post_type_archive($this->post_type)) {
            return plugin()->getPath() . 'templates/' . $this->templates['archive'];
        }
        return $template;
    }

    public function filter_taxonomy_template($template)
    {
        if (is_tax($this->post_type . '_category')) {
            return plugin()->getPath() . 'templates/' . $this->templates['taxonomy']['category'];
        }

        if (is_tax($this->post_type . '_tag')) {
            return plugin()->getPath() . 'templates/' . $this->templates['taxonomy']['tag'];
        }

        return $template;
    }

    /**
     * Flush rewrite rules if slug changed
     */
    public function checkSlugChange($old_value, $value)
    {
        $rewriteKeys = [
            'permalink_settings_custom_faq_slug',
            'permalink_settings_custom_faq_category_slug',
            'permalink_settings_custom_faq_tag_slug',
        ];

        foreach ($rewriteKeys as $key) {
            if (isset($old_value[$key], $value[$key]) && $old_value[$key] !== $value[$key]) {
                set_transient('rrze_faq_flush_rewrite_needed', true, 60);
                break;
            }
        }
    }

    public function maybeFlushRewriteRules()
    {
        if (get_transient('rrze_faq_flush_rewrite_needed')) {
            flush_rewrite_rules();
            delete_transient('rrze_faq_flush_rewrite_needed');
        }
    }

    /**
     * Check if request matches slug
     */
    public static function is_slug_request($slug): bool
    {
        if (empty($slug)) {
            return false;
        }

        global $wp;
        $request_path = trim($wp->request, '/');

        return strpos($request_path, trim($slug, '/')) === 0;
    }

    /**
     * Disable canonical redirect if redirect page exists
     */
    public function maybe_disable_canonical_redirect(): void
    {
        $options = get_option('rrze-answers');

        $slug = !empty($options['permalink_settings_custom_faq_slug'])
            ? sanitize_title($options['permalink_settings_custom_faq_slug'])
            : 'faq';

        $redirect_id = (int) ($options['permalink_settings_redirect_archivpage_uri'] ?? 0);

        if ($redirect_id > 0 && self::is_slug_request($slug)) {
            remove_filter('template_redirect', 'redirect_canonical');
        }
    }

    /**
     * Custom 404
     */
    public static function render_custom_404(): void
    {
        global $wp_query;

        $wp_query->set_404();
        status_header(404);
        nocache_headers();

        include get_404_template();
        exit;
    }

    /**
     * Handle CPT redirects
     */
    public function custom_cpt_404_message(): void
    {
        global $wp_query;

        $options = get_option('rrze-answers');

        $slug = !empty($options['permalink_settings_custom_faq_slug'])
            ? sanitize_title($options['permalink_settings_custom_faq_slug'])
            : 'faq';

        // CPT Single 404
        if (
            isset($wp_query->query_vars['post_type']) &&
            $wp_query->query_vars['post_type'] === $this->post_type &&
            empty($wp_query->post)
        ) {
            self::render_custom_404();
        }

        // Redirect archive slug
        if (self::is_slug_request($slug)) {

            $redirect_id = (int) ($options['permalink_settings_redirect_archivpage_uri'] ?? 0);

            if ($redirect_id > 0) {

                $post = get_post($redirect_id);

                if ($post && get_post_status($post) === 'publish') {

                    wp_redirect(get_permalink($post), 301);
                    exit;
                }
            }
        }
    }
}