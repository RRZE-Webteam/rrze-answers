<?php

namespace RRZE\Answers;

defined('ABSPATH') || exit;

class CPTFAQ extends CPT
{
    protected $post_type = 'rrze_faq';
    protected $rest_base = 'faq';
    protected $menu_icon = 'dashicons-editor-help';
    protected $slug_options = ['slug_option_key' => 'website_custom_faq_slug', 'default_slug' => 'faq'];
    protected $labels = [
        'name' => __('FAQ', 'rrze-faq'),
        'singular_name' => __('FAQ', 'rrze-faq'),
        'menu_name' => __('FAQ', 'rrze-faq'),
    ];
    protected $taxonomies = [
        ['name' => 'rrze_faq_category', 'label' => __('FAQ Categories', 'rrze-faq'), 'slug_option_key' => 'website_custom_faq_category_slug', 'default_slug' => 'faq_category', 'rest_base' => 'rrze_faq_category', 'hierarchical' => true],
        ['name' => 'rrze_faq_tag', 'label' => __('FAQ Tags', 'rrze-faq'), 'slug_option_key' => 'website_custom_faq_tag_slug', 'default_slug' => 'faq_tag', 'rest_base' => 'rrze_faq_tag', 'hierarchical' => false],
    ];
    protected $templates = [
        'single' => 'single-faq.php',
        'archive' => 'archive-faq.php',
        'taxonomy' => ['rrze_faq_category' => 'faq_category.php', 'rrze_faq_tag' => 'faq_tag.php']
    ];
}