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
    protected $menu_icon   = 'dashicons-translation';
    protected $slug_options = [
        'slug_option_key' => 'website_custom_placeholder_slug',
        'default_slug'    => 'placeholder'
    ];

    // Funktionsaufrufe wie __() dürfen NICHT direkt hier stehen
    protected $labels = [];
    protected $taxonomies = [];

    protected $supports = ['title'];




    public function __construct()
    {
        $this->labels = [
            'name' => _x('Placeholder', 'Placeholders', 'rrze-answers'),
            'singular_name' => _x('Placeholder', 'Single placeholder', 'rrze-answers'),
            'menu_name' => __('Placeholders', 'rrze-answers'),
            'add_new' => __('Add placeholder', 'rrze-answers'),
            'add_new_item' => __('Add new placeholder', 'rrze-answers'),
            'edit_item' => __('Edit placeholder', 'rrze-answers'),
            'all_items' => __('All placeholders', 'rrze-answers'),
            'search_items' => __('Search placeholder', 'rrze-answers'),
        ];

        parent::__construct($this->post_type);

    }
}
