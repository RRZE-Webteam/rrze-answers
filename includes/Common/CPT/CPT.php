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

    protected $args = [
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
        ];


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


        // 2DO: adjust to faq, glossary, synonym
        add_action('init', [$this, 'maybeFlushRewriteRules'], 20);
        add_action('update_option_rrze-answers', [$this, 'checkSlugChange'], 10, 2);

        add_action('template_redirect', [$this, 'maybe_disable_canonical_redirect'], 1);
        add_action('template_redirect', [$this, 'custom_cpt_404_message']);

    }


    // 2DO: doesn't work -> pre update options

    add_filter('rest_authentication_errors', function($result) {
    if (!empty($result)) {
        return $result;
    }
    $opts = (array) get_option('rrze-answers');
    $enabled = (($opts['api_active_' . $this->post_type] ?? 'off') === 'on');

    if (!$enabled) {
        $request = rest_get_server()->get_current_request();
        if ($request) {
            $route = $request->get_route();
            if (strpos($route, ENDPOINT) === 0) {
                return new WP_Error('forbidden', __('API für ist deaktiviert.', 'rrze-answers'), ['status' => 403]);
            }
        }
    }
    return $result;
});


    public function activateAPI($old, $new) {
    $enabled = (($new['api_active_' . $this->post_type] ?? 'off') === 'on');

    if ( post_type_exists($this->post_type) ) {
        unregister_post_type($this->post_type);
    }

        $options = get_option('rrze-answers');
        $slug_option_key = $this->slug_options['slug_option_key'];
        $default_slug = $this->slug_options['default_slug'];
        $slug = !empty($options[$slug_option_key]) ? sanitize_title($options[$slug_option_key]) : $default_slug;


    $args = [
        'label'        => 'Mein Posttype',
        'public'       => true,
        'show_in_rest' => $enabled,
        'rewrite' => ['slug' => $slug, 'with_front' => true],
        'show_in_rest' => $enabled
    ];


        if ($enabled) {
            $args['rest_base'] = $this->rest_base;
            $args['rest_controller_class'] = 'WP_REST_Posts_Controller';
        }    

    register_post_type('mein_posttype', $args);
}



    public function registerPostType()
    {

        register_post_type($this->post_type, $this->args);
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
        if ($post->post_type == $this->post_type) {
            $template = plugin()->getPath() . 'templates/' . $this->templates['single'];
        }
        return $template;
    }



    public function filter_archive_template($template)
    {
        global $post;
        if ($post->post_type == $this->post_type) {
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

    public function checkSlugChange($old_value, $value)
    {
        $rewriteKeys = [
            'permalink_settings_custom_faq_slug',
            'permalink_settings_custom_faq_category_slug',
            'permalink_settings_custom_faq_tag_slug',
        ];

        foreach ($rewriteKeys as $key) {
            if (isset($old_value[$key], $value[$key]) && $old_value[$key] !== $value[$key]) {
                set_transient('rrze_faq_flush_rewrite_needed', true, 60); // 1 minute is enough 
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

    public function rrze_faq_get_redirect_page_url($options): string
    {
        $redirect_id = isset($this->options['permalink_settings_redirect_archivpage_uri']) ? (int) $this->options['permalink_settings_redirect_archivpage_uri'] : 0;
        if ($redirect_id > 0) {
            $post = get_post($redirect_id);
            if ($post && get_post_status($post) === 'publish') {
                return get_permalink($redirect_id);
            }
        }
        return '';
    }

    public static function is_slug_request($slug): bool
    {
        if (empty($slug)) {
            return false;
        }

        global $wp;
        $request_path = trim($wp->request, '/');

        return $request_path === trim($slug, '/');
    }


    public function rrze_faq_redirect_if_needed(string $custom_slug): void
    {
        if (!self::is_slug_request($custom_slug)) {
            return;
        }

        $target_url = rrze_faq_get_redirect_page_url($this->options);
        if (!empty($target_url)) {
            wp_redirect(esc_url_raw($target_url), 301);
            exit;
        }
    }

    public function rrze_faq_disable_canonical_redirect_if_needed(string $custom_slug): void
    {
        if (!self::is_slug_request($custom_slug)) {
            return;
        }

        $target_url = rrze_faq_get_redirect_page_url($this->options);
        if (!empty($target_url)) {
            remove_filter('template_redirect', 'redirect_canonical');
        }
    }

    public function maybe_disable_canonical_redirect(): void
    {
        $this->options = $this->getOptions();
        $slug = !empty($this->options['permalink_settings_custom_faq_slug']) ? sanitize_title($this->options['permalink_settings_custom_faq_slug']) : 'rrze_faq';

        // Nur deaktivieren, wenn eine Weiterleitungsseite gesetzt ist UND exakt der Slug aufgerufen wird
        $redirect_id = (int) ($this->options['permalink_settings_redirect_archivpage_uri'] ?? 0);
        if ($redirect_id > 0 && self::is_slug_request($slug)) {
            remove_filter('template_redirect', 'redirect_canonical');
        }
    }

    public static function render_custom_404(): void
    {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        include get_404_template();
        exit;
    }

    public function custom_cpt_404_message(): void
    {
        global $wp_query;

        $this->options = $this->getOptions();
        $slug = !empty($this->options['permalink_settings_custom_faq_slug']) ? sanitize_title($this->options['permalink_settings_custom_faq_slug']) : 'rrze_faq';

        // CPT-Single 404
        if (
            isset($wp_query->query_vars['post_type']) &&
            $wp_query->query_vars['post_type'] === 'rrze_faq' &&
            empty($wp_query->post)
        ) {
            self::render_custom_404();
            return;
        }

        // Archiv-Slug direkt aufgerufen?
        if (self::is_slug_request($slug)) {
            $redirect_id = (int) ($this->options['permalink_settings_redirect_archivpage_uri'] ?? 0);

            if ($redirect_id > 0) {
                $post = get_post($redirect_id);
                if ($post && get_post_status($post) === 'publish') {
                    wp_redirect(esc_url_raw(get_permalink($post)), 301);
                    exit;
                }
            }
            // Andernfalls keine Weiterleitung, Archiv anzeigen lassen
        }
    }
}
