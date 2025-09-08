<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;


class CPTGlossary extends CPT
{
    protected $post_type = 'rrze_glossary';

    protected const TEMPLATES = [
        'single'  => 'glossary-single.php',
        'archive' => 'glossary-archive.php',
        'taxonomy' => [
            'rrze_glossary_category' => 'glossary-category.php',
            'rrze_glossary_tag'      => 'glossary-tag.php',
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
    protected $textdomain;

    public function __construct()
    {
        $this->labels = [
            'name' => _x('Glossary', 'Glossary entries', $this->textdomain),
            'singular_name' => _x('Glossary', 'Single glossary ', $this->textdomain),
            'menu_name' => __('Glossary', $this->textdomain),
            'add_new' => __('Add glossary', $this->textdomain),
            'add_new_item' => __('Add new glossary', $this->textdomain),
            'edit_item' => __('Edit glossary', $this->textdomain),
            'all_items' => __('All glossaries', $this->textdomain),
            'search_items' => __('Search glossary', $this->textdomain),
        ];

        $this->taxonomies = [
            [
                'name'            => 'rrze_glossary_category',
                'label'           => __('Glossary Categories', $this->textdomain),
                'slug_option_key' => 'website_custom_glossary_category_slug',
                'default_slug'    => 'glossary_category',
                'rest_base'       => 'rrze_glossary_category',
                'hierarchical'    => true,
            ],
            [
                'name'            => 'rrze_glossary_tag',
                'label'           => __('Glossary Tags', $this->textdomain),
                'slug_option_key' => 'website_custom_glossary_tag_slug',
                'default_slug'    => 'glossary_tag',
                'rest_base'       => 'rrze_glossary_tag',
                'hierarchical'    => false,
            ],
        ];

        parent::__construct($this->post_type);

        add_filter('single_template', [$this, 'filter_single_template']);
        add_filter('archive_template', [$this, 'filter_archive_template']);
        add_filter('taxonomy_template', [$this, 'filter_taxonomy_template']);
    }

    public function filter_single_template($template)
    {
        global $post;

        if ('rrze_glossary' === $post->post_type) {
            $template = plugin()->getPath() . 'templates/glossary-single.php';
        }
        return $template;
    }



    public function filter_archive_template($template)
    {
        if (is_post_type_archive('rrze_glossary')) {
            $template = plugin_dir_path(__DIR__) . 'templates/glossary-archive.php';
        }
        return $template;
    }


    public function filter_taxonomy_template($template)
    {
        if (is_tax('rrze_glossary_category')) {
            $template = plugin_dir_path(__DIR__) . 'templates/glossary-category.php';
        } elseif (is_tax('rrze_glossary_tag')) {
            $template = plugin_dir_path(__DIR__) . 'templates/glossary-tag.php';
        }
        return $template;
    }    
}
