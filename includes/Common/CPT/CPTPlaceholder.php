<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;

class CPTPlaceholder extends CPT
{
    protected $post_type = 'rrze_placeholder';
    protected $templates = [
        'single'  => 'rrze_placeholder-single.php',
        'archive' => 'rrze_placeholder-archive.php',
    ];

    protected $rest_base   = 'placeholder';
    protected $menu_icon   = 'dashicons-editor-paste-text';
    protected $slug_options = [
        'slug_option_key' => 'website_custom_placeholder_slug',
        'default_slug'    => 'placeholder'
    ];

    protected $labels = [];
    protected $taxonomies = [];

    protected $supports = ['title', 'editor', 'revisions'];




    public function __construct()
    {
        $this->labels = [
            'name' => _x('Placeholder', 'Placeholders', 'rrze-answers'),
            'singular_name' => _x('Placeholder', 'Single placeholder', 'rrze-answers'),
            'menu_name' => __('Placeholder', 'rrze-answers'),
            'add_new' => __('Add placeholder', 'rrze-answers'),
            'add_new_item' => __('Add new placeholder', 'rrze-answers'),
            'edit_item' => __('Edit placeholder', 'rrze-answers'),
            'all_items' => __('All placeholders', 'rrze-answers'),
            'search_items' => __('Search placeholder', 'rrze-answers'),
        ];

        parent::__construct($this->post_type);

        add_action('template_redirect', [$this, 'maybe_disable_canonical_redirect'], 1);
        add_action('template_redirect', [$this, 'custom_cpt_404_message'], 10);

    }

    /**
     * Disable canonical redirect if redirect page exists
     */
    public function maybe_disable_canonical_redirect(): void
    {
        $options = get_option('rrze-answers');

        $slug = !empty($options['custom_placeholder_slug'])
            ? sanitize_title($options['custom_placeholder_slug'])
            : 'glossary';

        $redirect_id = (int) ($options['redirect_archivpage_uri_placeholder'] ?? 0);

        if ($redirect_id > 0 && self::is_slug_request($slug)) {
            remove_filter('template_redirect', 'redirect_canonical');
        }
    }

    /**
     * Handle CPT redirects
     */
    public function custom_cpt_404_message(): void
    {
        global $wp_query;

        $options = get_option('rrze-answers');

        $slug = !empty($options['custom_placeholder_slug'])
            ? sanitize_title($options['custom_placeholder_slug'])
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

            $redirect_id = (int) ($options['redirect_archivpage_uri_placeholder'] ?? 0);

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
