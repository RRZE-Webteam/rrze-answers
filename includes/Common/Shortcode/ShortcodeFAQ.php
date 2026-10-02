<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

class ShortcodeFAQ extends AbstractQuestionShortcode
{
    public function __construct()
    {
        parent::__construct('faq');
    }

    public function getShortcodeSettings(): array
    {
        $ret = [
            'block' => [
                'blocktype' => 'rrze-answers/faq',
                'blockname' => 'rrze_faq',
                'title' => 'RRZE FAQ',
                'rrze_category' => 'widgets',
                'icon' => 'editor-help',
                'tinymce_icon' => 'help'
            ],
            'glossary' => [
                'values' => [
                    [
                        'id' => '',
                        'val' => __('none', 'rrze-answers')
                    ],
                    [
                        'id' => 'rrze_category',
                        'val' => __('Categories', 'rrze-answers')
                    ],
                    [
                        'id' => 'rrze_tag',
                        'val' => __('Tags', 'rrze-answers')
                    ]
                ],
                'default' => '',
                'field_type' => 'select',
                'label' => __('Glossary content', 'rrze-answers'),
                'type' => 'string'
            ],
            'glossarystyle' => [
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
                'label' => __('Glossary style', 'rrze-answers'),
                'type' => 'string'
            ],
            'category' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Categories', 'rrze-answers'),
                'type' => 'text'
            ],
            'tag' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Tags', 'rrze-answers'),
                'type' => 'text'
            ],
            'domain' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Domain', 'rrze-answers'),
                'type' => 'text'
            ],
            'id' => [
                'default' => NULL,
                'field_type' => 'text',
                'label' => 'FAQ',
                'type' => 'number'
            ],
            'hide_accordion' => [
                'field_type' => 'toggle',
                'label' => __('Hide accordeon', 'rrze-answers'),
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
                'label' => __('Load website with opened accordeons', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'color' => [
                'values' => [
                    [
                        'id' => 'fau',
                        'val' => 'fau'
                    ],
                    [
                        'id' => 'med',
                        'val' => 'med'
                    ],
                    [
                        'id' => 'nat',
                        'val' => 'nat'
                    ],
                    [
                        'id' => 'phil',
                        'val' => 'phil'
                    ],
                    [
                        'id' => 'rw',
                        'val' => 'rw'
                    ],
                    [
                        'id' => 'tf',
                        'val' => 'tf'
                    ],
                ],
                'default' => 'fau',
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
            'additional_class' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Additonal CSS-class(es) for sourrounding DIV', 'rrze-answers'),
                'type' => 'text'
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

        return $ret;
    }
}
