<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;

class CPTFAQ extends CPT
{
    protected $post_type = 'rrze_faq';
    protected $templates= [
        'single'  => 'rrze_faq-single.php',
        'archive' => 'rrze_faq-archive.php',
        'taxonomy' => [
            'category' => 'rrze_faq_category.php',
            'tag'      => 'rrze_faq_tag.php',
        ],
    ];
    protected $rest_base  = 'faq';
    protected $menu_icon  = 'dashicons-editor-help';
    protected $slug_options = [
        'slug_option_key' => 'website_custom_faq_slug',
        'default_slug'    => 'faq',
    ];

    protected $labels = [];
    protected $taxonomies = [];

    protected $supports = ['title', 'editor', 'revisions'];


    public function __construct()
    {
        $this->labels = [
            'name' => _x('FAQ', 'FAQ, synonym or glossary entries', 'rrze-answers'),
            'singular_name' => _x('FAQ', 'Single FAQ, synonym or glossary ', 'rrze-answers'),
            'menu_name' => __('FAQ', 'rrze-answers'),
            'add_new' => __('Add FAQ', 'rrze-answers'),
            'add_new_item' => __('Add new FAQ', 'rrze-answers'),
            'edit_item' => __('Edit FAQ', 'rrze-answers'),
            'all_items' => __('All FAQ', 'rrze-answers'),
            'search_items' => __('Search FAQ', 'rrze-answers'),
        ];

        $this->taxonomies = [
            [
                'name'            => 'rrze_faq_category',
                'label'           => __('FAQ Categories', 'rrze-answers'),
                'slug_option_key' => 'website_custom_faq_category_slug',
                'default_slug'    => 'faq_category',
                'rest_base'       => 'rrze_faq_category',
                'hierarchical'    => true,
            ],
            [
                'name'            => 'rrze_faq_tag',
                'label'           => __('FAQ Tags', 'rrze-answers'),
                'slug_option_key' => 'website_custom_faq_tag_slug',
                'default_slug'    => 'faq_tag',
                'rest_base'       => 'rrze_faq_tag',
                'hierarchical'    => false,
            ],
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

        $slug = !empty($options['permalink_settings_custom_faq_slug'])
            ? sanitize_title($options['permalink_settings_custom_faq_slug'])
            : 'faq';

        $redirect_id = (int) ($options['permalink_settings_redirect_archivpage_uri_faq'] ?? 0);

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

            $redirect_id = (int) ($options['permalink_settings_redirect_archivpage_uri_faq'] ?? 0);

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
