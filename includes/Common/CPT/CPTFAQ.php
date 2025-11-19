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
    protected $taxonomies = [
        'rrze_faq_category', 
        'rrze_faq_tag'
    ];


    public function __construct()
    {
        $this->labels = [
            'name' => _x('FAQ', 'FAQ, placeholder or glossary entries', 'rrze-answers'),
            'singular_name' => _x('FAQ', 'Single FAQ, placeholder or glossary ', 'rrze-answers'),
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
    }

}
