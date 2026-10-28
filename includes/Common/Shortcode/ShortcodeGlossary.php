<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

class ShortcodeGlossary extends AbstractQuestionShortcode
{
    public function __construct()
    {
        parent::__construct('glossary', ['fau_glossar']);
    }

    public function getShortcodeSettings(): array
    {
        return [
            'block' => [
                'blocktype' => 'rrze-answers/glossary',
                'blockname' => 'glossary',
                'title' => 'RRZE Glossary',
                'category' => 'widgets',
                'icon' => 'book',
                'tinymce_icon' => 'info',
            ],
            'register' => [
                'values' => [
                    [
                        'id' => '',
                        'val' => __('none', 'rrze-answers')
                    ],
                    [
                        'id' => 'category',
                        'val' => __('Categories', 'rrze-answers')
                    ],
                    [
                        'id' => 'tag',
                        'val' => __('Tags', 'rrze-answers')
                    ]
                ],
                'default' => '',
                'field_type' => 'select',
                'label' => __('Register content', 'rrze-answers'),
                'type' => 'string'
            ],
            'registerstyle' => [
                'values' => [
                    [
                        'id' => '',
                        'val' => __('-- hidden --', 'rrze-answers')
                    ],
                    [
                        'id' => 'a-z',
                        'val' => __('A - Z', 'rrze-answers')
                    ],
                    [
                        'id' => 'tagcloud',
                        'val' => __('Tagcloud', 'rrze-answers')
                    ],
                    [
                        'id' => 'tabs',
                        'val' => __('Tabs', 'rrze-answers')
                    ]
                ],
                'default' => '',
                'field_type' => 'select',
                'label' => __('Register style', 'rrze-answers'),
                'type' => 'string'
            ],
            'category' => [
                'default' => '0',
                'field_type' => 'text',
                'label' => __('Categories', 'rrze-answers'),
                'type' => 'string'
            ],
            'tag' => [
                'default' => 0,
                'field_type' => 'text',
                'label' => __('Tags', 'rrze-answers'),
                'type' => 'string'
            ],
            'id' => [
                'default' => NULL,
                'field_type' => 'text',
                'label' => __('Glossary', 'rrze-answers'),
                'type' => 'number'
            ],
            'masonry' => [
                'field_type' => 'toggle',
                'label' => __('Grid', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'search' => [
                'field_type' => 'toggle',
                'label' => __('Show search field', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'hide_accordion' => [
                'field_type' => 'toggle',
                'label' => __('Hide accordion', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'hide_title' => [
                'field_type' => 'toggle',
                'label' => __('Hide title', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'expand_all_link' => [
                'field_type' => 'toggle',
                'label' => __('Show "expand all" button', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'load_open' => [
                'field_type' => 'toggle',
                'label' => __('Load website with opened accordions', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'color' => [
                'values' => [
                    [
                        'id' => 'med',
                        'val' => 'med'
                    ],
                    [
                        'id' => 'nat',
                        'val' => 'nat'
                    ],
                    [
                        'id' => 'rw',
                        'val' => 'rw'
                    ],
                    [
                        'id' => 'phil',
                        'val' => 'phil'
                    ],
                    [
                        'id' => 'tk',
                        'val' => 'tk'
                    ],
                ],
                'default' => 'tk',
                'field_type' => 'select',
                'label' => __('Color', 'rrze-answers'),
                'type' => 'string'
            ],
            'style' => [
                'values' => [
                    [
                        'id' => '',
                        'val' => __('none', 'rrze-answers')
                    ],
                    [
                        'id' => 'light',
                        'val' => 'light'
                    ],
                    [
                        'id' => 'dark',
                        'val' => 'dark'
                    ],
                ],
                'default' => '',
                'field_type' => 'select',
                'label' => __('Style', 'rrze-answers'),
                'type' => 'string'
            ],
            'additional_class' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Additonal CSS-class(es) for sourrounding DIV', 'rrze-answers'),
                'type' => 'string'
            ],
            'lang' => [
                'default' => '',
                'field_type' => 'select',
                'label' => __('Language', 'rrze-answers'),
                'type' => 'string'
            ],
            'sort' => [
                'values' => [
                    [
                        'id' => 'title',
                        'val' => __('Title', 'rrze-answers')
                    ],
                    [
                        'id' => 'id',
                        'val' => __('ID', 'rrze-answers')
                    ],
                    [
                        'id' => 'sortfield',
                        'val' => __('Sort field', 'rrze-answers')
                    ],
                ],
                'default' => 'title',
                'field_type' => 'select',
                'label' => __('Sort', 'rrze-answers'),
                'type' => 'string'
            ],
            'order' => [
                'values' => [
                    [
                        'id' => 'ASC',
                        'val' => __('ASC', 'rrze-answers')
                    ],
                    [
                        'id' => 'DESC',
                        'val' => __('DESC', 'rrze-answers')
                    ],
                ],
                'default' => 'ASC',
                'field_type' => 'select',
                'label' => __('Order', 'rrze-answers'),
                'type' => 'string'
            ],
            'hstart' => [
                'default' => 2,
                'field_type' => 'text',
                'label' => __('Heading level of the first heading', 'rrze-answers'),
                'type' => 'number'
            ],
        ];
    }
}
