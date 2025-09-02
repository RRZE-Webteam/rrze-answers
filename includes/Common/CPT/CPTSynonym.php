<?php

namespace RRZE\Answers\Common\CPT;

defined('ABSPATH') || exit;

class CPTSynonym extends CPT
{
    protected $post_type   = 'rrze_synonym';
    protected $rest_base   = 'synonym';
    protected $menu_icon   = 'dashicons-translation';
    protected $slug_options = [
        'slug_option_key' => 'website_custom_synonym_slug',
        'default_slug'    => 'synonym'
    ];

    // Funktionsaufrufe wie __() dürfen NICHT direkt hier stehen
    protected $labels = [];
    protected $taxonomies = [];

    protected $templates = [
        'single'   => 'single-synonym.php',
        'archive'  => 'archive-synonym.php',
        'taxonomy' => [
            'rrze_synonym_group' => 'synonym_group.php',
            'rrze_synonym_tag'   => 'synonym_tag.php'
        ]
    ];

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

        $this->taxonomies = [
            [
                'name'            => 'rrze_synonym_group',
                'label'           => __('Synonym Groups', 'rrze-faq'),
                'slug_option_key' => 'website_custom_synonym_group_slug',
                'default_slug'    => 'synonym_group',
                'rest_base'       => 'rrze_synonym_group',
                'hierarchical'    => true,
            ],
            [
                'name'            => 'rrze_synonym_tag',
                'label'           => __('Synonym Tags', 'rrze-faq'),
                'slug_option_key' => 'website_custom_synonym_tag_slug',
                'default_slug'    => 'synonym_tag',
                'rest_base'       => 'rrze_synonym_tag',
                'hierarchical'    => false,
            ],
        ];
    }
}
