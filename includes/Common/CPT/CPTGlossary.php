<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;


class CPTGlossary extends CPT
{
    protected $post_type = 'rrze_glossary';

    protected $templates = [
        'single'  => 'rrze_glossary-single.php',
        'archive' => 'rrze_glossary-archive.php',
        'taxonomy' => [
            'category' => 'rrze_glossary_category.php',
            'tag'      => 'rrze_glossary_tag.php',
        ],
    ];

    protected $rest_base  = 'glossary';
    protected $menu_icon  = 'dashicons-book-alt';
    protected $slug_options = [
        'slug_option_key' => 'website_custom_glossary_slug',
        'default_slug'    => 'glossary',
    ];

    protected $labels = [];
    protected $taxonomies = [];

    public function __construct()
    {
        $this->labels = [
            'name' => _x('Glossary', 'Glossary entries', 'rrze-answers'),
            'singular_name' => _x('Glossary', 'Single glossary ', 'rrze-answers'),
            'menu_name' => __('Glossary', 'rrze-answers'),
            'add_new' => __('Add glossary', 'rrze-answers'),
            'add_new_item' => __('Add new glossary', 'rrze-answers'),
            'edit_item' => __('Edit glossary', 'rrze-answers'),
            'all_items' => __('All glossaries', 'rrze-answers'),
            'search_items' => __('Search glossary', 'rrze-answers'),
        ];

        $this->taxonomies = [
            [
                'name'            => 'rrze_glossary_category',
                'label'           => __('Glossary Categories', 'rrze-answers'),
                'slug_option_key' => 'website_custom_glossary_category_slug',
                'default_slug'    => 'glossary_category',
                'rest_base'       => 'rrze_glossary_category',
                'hierarchical'    => true,
            ],
            [
                'name'            => 'rrze_glossary_tag',
                'label'           => __('Glossary Tags', 'rrze-answers'),
                'slug_option_key' => 'website_custom_glossary_tag_slug',
                'default_slug'    => 'glossary_tag',
                'rest_base'       => 'rrze_glossary_tag',
                'hierarchical'    => false,
            ],
        ];

        parent::__construct($this->post_type);

    }
}
