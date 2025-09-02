<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;


class CPTGlossary extends CPT
{
    protected $post_type  = 'rrze_glossary';
    protected $rest_base  = 'glossary';
    protected $menu_icon  = 'dashicons-book-alt';
    protected $slug_options = [
        'slug_option_key' => 'website_custom_glossary_slug',
        'default_slug'    => 'glossary',
    ];

    protected $labels = [];
    protected $taxonomies = [];

    protected $templates = [
        'single'   => 'single-glossary.php',
        'archive'  => 'archive-glossary.php',
        'taxonomy' => [
            'rrze_glossary_category' => 'glossary_category.php',
            'rrze_glossary_tag'      => 'glossary_tag.php',
        ],
    ];

    public function __construct()
    {
        // Call parent constructor if needed
        if (is_callable(['parent', '__construct'])) {
            parent::__construct();
        }

        $this->labels = [
            'name'          => __('Glossary', 'rrze-glossary'),
            'singular_name' => __('Glossary', 'rrze-glossary'),
            'menu_name'     => __('Glossary', 'rrze-glossary'),
        ];

        $this->taxonomies = [
            [
                'name'            => 'rrze_glossary_category',
                'label'           => __('Glossary Categories', 'rrze-glossary'),
                'slug_option_key' => 'website_custom_glossary_category_slug',
                'default_slug'    => 'glossary_category',
                'rest_base'       => 'rrze_glossary_category',
                'hierarchical'    => true,
            ],
            [
                'name'            => 'rrze_glossary_tag',
                'label'           => __('Glossary Tags', 'rrze-glossary'),
                'slug_option_key' => 'website_custom_glossary_tag_slug',
                'default_slug'    => 'glossary_tag',
                'rest_base'       => 'rrze_glossary_tag',
                'hierarchical'    => false,
            ],
        ];
    }
}
