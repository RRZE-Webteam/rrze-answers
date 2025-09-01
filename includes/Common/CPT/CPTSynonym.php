<?php

namespace RRZE\Answers;

defined('ABSPATH') || exit;

class CPTSynonym extends CPT
{
    protected $post_type = 'rrze_synonym';
    protected $rest_base = 'synonym';
    protected $menu_icon = 'dashicons-translation';
    protected $slug_options = ['slug_option_key'=>'website_custom_synonym_slug','default_slug'=>'synonym'];
    protected $labels = [
        'name' => __('Synonyms','rrze-faq'),
        'singular_name' => __('Synonym','rrze-faq'),
        'menu_name' => __('Synonyms','rrze-faq'),
    ];
    protected $taxonomies = [
        ['name'=>'rrze_synonym_group','label'=>__('Synonym Groups','rrze-faq'),'slug_option_key'=>'website_custom_synonym_group_slug','default_slug'=>'synonym_group','rest_base'=>'rrze_synonym_group','hierarchical'=>true],
        ['name'=>'rrze_synonym_tag','label'=>__('Synonym Tags','rrze-faq'),'slug_option_key'=>'website_custom_synonym_tag_slug','default_slug'=>'synonym_tag','rest_base'=>'rrze_synonym_tag','hierarchical'=>false],
    ];
    protected $templates = [
        'single'=>'single-synonym.php','archive'=>'archive-synonym.php',
        'taxonomy'=>['rrze_synonym_group'=>'synonym_group.php','rrze_synonym_tag'=>'synonym_tag.php']
    ];
}