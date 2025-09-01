<?php

namespace RRZE\Answers;

defined('ABSPATH') || exit;

class CPTGlossary extends CPT
{
    protected $post_type = 'rrze_glossary';
    protected $rest_base = 'glossary';
    protected $menu_icon = 'dashicons-book-alt';
    protected $slug_options = ['slug_option_key' => 'website_custom_glossary_slug', 'default_slug' => 'glossary'];
    protected $labels = [
        'name' => __('Glossary', 'rrze-faq'),
        'singular_name' => __('Glossary', 'rrze-faq'),
        'menu_name' => __('Glossary', 'rrze-faq'),
    ];
    protected $taxonomies = [
        ['name' => 'rrze_glossary_category', 'label' => __('Glossary Categories', 'rrze-faq'), 'slug_option_key' => 'website_custom_glossary_category_slug', 'default_slug' => 'glossary_category', 'rest_base' => 'rrze_glossary_category', 'hierarchical' => true],
        ['name' => 'rrze_glossary_tag', 'label' => __('Glossary Tags', 'rrze-faq'), 'slug_option_key' => 'website_custom_glossary_tag_slug', 'default_slug' => 'glossary_tag', 'rest_base' => 'rrze_glossary_tag', 'hierarchical' => false],
    ];
    protected $templates = [
        'single' => 'single-glossary.php',
        'archive' => 'archive-glossary.php',
        'taxonomy' => ['rrze_glossary_category' => 'glossary_category.php', 'rrze_glossary_tag' => 'glossary_tag.php']
    ];
}
