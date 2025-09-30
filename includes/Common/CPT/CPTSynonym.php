<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;

class CPTSynonym extends CPT
{
    protected $post_type = 'rrze_synonym';
    protected $templates = [
        'single'  => 'rrze_synonym-single.php',
        'archive' => 'rrze_synonym-archive.php',
    ];

    protected $rest_base   = 'synonym';
    protected $menu_icon   = 'dashicons-translation';
    protected $slug_options = [
        'slug_option_key' => 'website_custom_synonym_slug',
        'default_slug'    => 'synonym'
    ];

    // Funktionsaufrufe wie __() dürfen NICHT direkt hier stehen
    protected $labels = [];
    protected $taxonomies = [];

    protected $supports = ['title'];




    public function __construct()
    {
        $this->labels = [
            'name' => _x('Synonym', 'Synonyms', 'rrze-answers'),
            'singular_name' => _x('Synonym', 'Single synonym', 'rrze-answers'),
            'menu_name' => __('Synonyms', 'rrze-answers'),
            'add_new' => __('Add synonym', 'rrze-answers'),
            'add_new_item' => __('Add new synonym', 'rrze-answers'),
            'edit_item' => __('Edit synonym', 'rrze-answers'),
            'all_items' => __('All synonyms', 'rrze-answers'),
            'search_items' => __('Search synonym', 'rrze-answers'),
        ];

        parent::__construct($this->post_type);

    }
}
