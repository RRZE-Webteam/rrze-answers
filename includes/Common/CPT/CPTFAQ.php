<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;


class CPTFAQ extends CPT
{
    public const POST_TYPE = 'rrze_faq';
    protected const TEMPLATES = [
        'single'  => 'single-faq.php',
        'archive' => 'archive-faq.php',
        'taxonomy' => [
            'rrze_faq_category' => 'faq_category.php',
            'rrze_faq_tag'      => 'faq_tag.php',
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

    protected $textdomain;

    public function __construct($textdomain)
    {
        $this->textdomain = $textdomain;

        parent::__construct($this->textdomain);

        $this->labels = [
            'name' => _x('FAQ', 'FAQ, synonym or glossary entries', $this->textdomain),
            'singular_name' => _x('FAQ', 'Single FAQ, synonym or glossary ', $this->textdomain),
            'menu_name' => __('FAQ', $this->textdomain),
            'add_new' => __('Add FAQ', $this->textdomain),
            'add_new_item' => __('Add new FAQ', $this->textdomain),
            'edit_item' => __('Edit FAQ', $this->textdomain),
            'all_items' => __('All FAQ', $this->textdomain),
            'search_items' => __('Search FAQ', $this->textdomain),
        ];

        $this->taxonomies = [
            [
                'name'            => 'rrze_faq_category',
                'label'           => __('FAQ Categories', $this->textdomain),
                'slug_option_key' => 'website_custom_faq_category_slug',
                'default_slug'    => 'faq_category',
                'rest_base'       => 'rrze_faq_category',
                'hierarchical'    => true,
            ],
            [
                'name'            => 'rrze_faq_tag',
                'label'           => __('FAQ Tags', $this->textdomain),
                'slug_option_key' => 'website_custom_faq_tag_slug',
                'default_slug'    => 'faq_tag',
                'rest_base'       => 'rrze_faq_tag',
                'hierarchical'    => false,
            ],
        ];
    }
}
