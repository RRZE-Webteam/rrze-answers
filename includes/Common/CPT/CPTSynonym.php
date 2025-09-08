<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;

class CPTSynonym extends CPT
{
    public const POST_TYPE = 'rrze_synonym';
    protected const TEMPLATES = [
        'single'  => 'synonym-single.php',
        'archive' => 'synonym-archive.php',
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

    protected $textdomain;


    public function __construct($textdomain)
    {
        $this->textdomain = $textdomain;

        parent::__construct($this->textdomain);

        $this->labels = [
            'name' => _x('Synonym', 'Synonyms', $this->textdomain),
            'singular_name' => _x('Synonym', 'Single synonym', $this->textdomain),
            'menu_name' => __('Synonyms', $this->textdomain),
            'add_new' => __('Add synonym', $this->textdomain),
            'add_new_item' => __('Add new synonym', $this->textdomain),
            'edit_item' => __('Edit synonym', $this->textdomain),
            'all_items' => __('All synonyms', $this->textdomain),
            'search_items' => __('Search synonym', $this->textdomain),
        ];
    }
}
