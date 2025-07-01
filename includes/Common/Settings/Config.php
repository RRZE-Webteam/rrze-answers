<?php

namespace RRZE\FAQ;

use function __;

defined('ABSPATH') || exit;

class Config
{
    public static function getOptionName(): string
    {
        return 'rrze-faq';
    }

    public static function getConstants(?string $key = null): array|string|null
    {
        $options = [
            'cpt' => [
                'faq' => 'rrze_faq',
                'category' => 'rrze_faq_category',
                'tag' => 'rrze_faq_tag'
            ],
            'langcodes' => [
                'de' => __('German', 'rrze-faq'),
                'en' => __('English', 'rrze-faq'),
                'es' => __('Spanish', 'rrze-faq'),
                'fr' => __('French', 'rrze-faq'),
                'ru' => __('Russian', 'rrze-faq'),
                'zh' => __('Chinese', 'rrze-faq')
            ],
            'schema' => [
                'RRZE_SCHEMA_START' => '<div itemscope itemtype="https://schema.org/FAQPage">',
                'RRZE_SCHEMA_END' => '</div>',
                'RRZE_SCHEMA_QUESTION_START' => '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question"><div itemprop="name">',
                'RRZE_SCHEMA_QUESTION_END' => '</div>',
                'RRZE_SCHEMA_ANSWER_START' => '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer"><div itemprop="text">',
                'RRZE_SCHEMA_ANSWER_END' => '</div></div></div>',
            ]
        ];

        return $key !== null && array_key_exists($key, $options) ? $options[$key] : $options;
    }

    public static function getMenuSettings(): array
    {
        return [
            'page_title' => __('RRZE FAQ', 'rrze-faq'),
            'menu_title' => __('RRZE FAQ', 'rrze-faq'),
            'capability' => 'manage_options',
            'menu_slug' => 'rrze-faq',
            'title' => __('RRZE FAQ Settings', 'rrze-faq'),
        ];
    }

    public static function getHelpTab(): array
    {
        return [[
            'id' => 'rrze-faq-help',
            'content' => ['<p>' . __('Here comes the Context Help content.', 'rrze-faq') . '</p>'],
            'title' => __('Overview', 'rrze-faq'),
            'sidebar' => sprintf(
                '<p><strong>%1$s:</strong></p><p><a href="https://blogs.fau.de/webworking">RRZE Webworking</a></p><p><a href="https://github.com/RRZE Webteam">%2$s</a></p>',
                __('For more information', 'rrze-faq'),
                __('RRZE Webteam on Github', 'rrze-faq')
            )
        ]];
    }

    public static function getSections(): array
    {
        return [
            ['id' => 'doms', 'title' => __('Domains', 'rrze-faq')],
            ['id' => 'faqsync', 'title' => __('Synchronize', 'rrze-faq')],
            ['id' => 'website', 'title' => __('Website', 'rrze-faq')],
            ['id' => 'faqlog', 'title' => __('Logfile', 'rrze-faq')]
        ];
    }

    public static function getPageList(): array
    {
        $pages = \get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'asc',
            'post_status' => 'publish'
        ]);

        $options = ['' => __('Default archive', 'rrze-faq')];
        foreach ($pages as $page) {
            $options[get_permalink($page->ID)] = $page->post_title;
        }
        return $options;
    }

    /**
 * Gibt die Einstellungen der Optionsfelder zurück.
 * @return array [description]
 */

public static function getFields():array
{
	return [
		'doms' => [
			[
				'name' => 'new_name',
				'label' => __('Short name', 'rrze-faq'),
				'desc' => __('Enter a short name for this domain.', 'rrze-faq'),
				'type' => 'text'
			],
			[
				'name' => 'new_url',
				'label' => __('URL', 'rrze-faq'),
				'desc' => __('Enter the domain\'s URL you want to receive FAQ from.', 'rrze-faq'),
				'type' => 'text'
			]
		],
		'faqsync' => [
			[
				'name' => 'shortname',
				'label' => __('Short name', 'rrze-faq'),
				'desc' => __('Use this name as attribute \'domain\' in shortcode [faq]', 'rrze-faq'),
				'type' => 'plaintext',
				'default' => ''
			],
			[
				'name' => 'url',
				'label' => __('URL', 'rrze-faq'),
				'desc' => '',
				'type' => 'plaintext',
				'default' => ''
			],
			[
				'name' => 'categories',
				'label' => __('Categories', 'rrze-faq'),
				'desc' => __('Please select the categories you\'d like to fetch FAQ to.', 'rrze-faq'),
				'type' => 'multiselect',
				'options' => []
			],
			[
				'name' => 'donotsync',
				'label' => __('Synchronize', 'rrze-faq'),
				'desc' => __('Do not synchronize', 'rrze-faq'),
				'type' => 'checkbox',
			],
			[
				'name' => 'hr',
				'label' => '',
				'desc' => '',
				'type' => 'line'
			],
			[
				'name' => 'info',
				'label' => __('Info', 'rrze-faq'),
				'desc' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-faq'),
				'type' => 'plaintext',
				'default' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-faq'),
			],
			[
				'name' => 'autosync',
				'label' => __('Mode', 'rrze-faq'),
				'desc' => __('Synchronize automatically', 'rrze-faq'),
				'type' => 'checkbox',
			],
			[
				'name' => 'frequency',
				'label' => __('Frequency', 'rrze-faq'),
				'desc' => '',
				'default' => 'daily',
				'options' => [
					'daily' => __('daily', 'rrze-faq'),
					'twicedaily' => __('twicedaily', 'rrze-faq')
				],
				'type' => 'select'
			],
		],
		'website' => [
			[
				'name' => 'redirect_archivpage_uri',
				'label' => __('Custom archive page', 'rrze-faq'),
				'desc' => '',
				'type' => 'select',
                'options' => self::getPageList(),				
				'default' => ''
			],
			[
				'name' => 'custom_faq_slug',
				'label' => __('Custom FAQ Slug', 'rrze-faq'),
				'desc' => '',
				'type' => 'text',
				'default' => 'faq',
				'placeholder' => 'faq'
			],
			[
				'name' => 'custom_faq_category_slug',
				'label' => __('Custom FAQ Category Slug', 'rrze-faq'),
				'desc' => '',
				'type' => 'text',
				'default' => 'faq_category',
				'placeholder' => 'faq_category'

			],
			[
				'name' => 'custom_faq_tag_slug',
				'label' => __('Custom FAQ Tag Slug', 'rrze-faq'),
				'desc' => '',
				'type' => 'text',
				'default' => 'faq_tag',
				'placeholder' => 'faq_tag'
			],
		],
		'faqlog' => [
			[
				'name' => 'faqlogfile',
				'type' => 'logfile',
				'default' => FAQLOGFILE
			]
		]
	];
}


/**
 * Gibt die Einstellungen der Parameter für Shortcode für den klassischen Editor und für Gutenberg zurück.
 * @return array [description]
 */

public static function getShortcodeSettings():array
{
	$ret = [
		'block' => [
			'blocktype' => 'rrze-faq/faq',
			'blockname' => 'faq',
			'title' => 'RRZE FAQ',
			'category' => 'widgets',
			'icon' => 'editor-help',
			'tinymce_icon' => 'help'
		],
		'glossary' => [
			'values' => [
				[
					'id' => '',
					'val' => __('none', 'rrze-faq')
				],
				[
					'id' => 'category',
					'val' => __('Categories', 'rrze-faq')
				],
				[
					'id' => 'tag',
					'val' => __('Tags', 'rrze-faq')
				]
			],
			'default' => '',
			'field_type' => 'select',
			'label' => __('Glossary content', 'rrze-faq'),
			'type' => 'string'
		],
		'glossarystyle' => [
			'values' => [
				[
					'id' => '',
					'val' => __('-- hidden --', 'rrze-faq')
				],
				[
					'id' => 'a-z',
					'val' => __('A - Z', 'rrze-faq')
				],
				[
					'id' => 'tagcloud',
					'val' => __('Tagcloud', 'rrze-faq')
				],
				[
					'id' => 'tabs',
					'val' => __('Tabs', 'rrze-faq')
				]
			],
			'default' => 'a-z',
			'field_type' => 'select',
			'label' => __('Glossary style', 'rrze-faq'),
			'type' => 'string'
		],
		'category' => [
			'default' => '',
			'field_type' => 'text',
			'label' => __('Categories', 'rrze-faq'),
			'type' => 'text'
		],
		'tag' => [
			'default' => '',
			'field_type' => 'text',
			'label' => __('Tags', 'rrze-faq'),
			'type' => 'text'
		],
		'domain' => [
			'default' => '',
			'field_type' => 'text',
			'label' => __('Domain', 'rrze-faq'),
			'type' => 'text'
		],
		'id' => [
			'default' => NULL,
			'field_type' => 'text',
			'label' => __('FAQ', 'rrze-faq'),
			'type' => 'number'
		],
		'hide_accordion' => [
			'field_type' => 'toggle',
			'label' => __('Hide accordeon', 'rrze-faq'),
			'type' => 'boolean',
			'default' => FALSE,
			'checked' => FALSE
		],
		'hide_title' => [
			'field_type' => 'toggle',
			'label' => __('Hide title', 'rrze-faq'),
			'type' => 'boolean',
			'default' => FALSE,
			'checked' => FALSE
		],
		'expand_all_link' => [
			'field_type' => 'toggle',
			'label' => __('Show "expand all" button', 'rrze-faq'),
			'type' => 'boolean',
			'default' => FALSE,
			'checked' => FALSE
		],
		'load_open' => [
			'field_type' => 'toggle',
			'label' => __('Load website with opened accordeons', 'rrze-faq'),
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
			'label' => __('Color', 'rrze-faq'),
			'type' => 'string'
		],
		'style' => [
			'values' => [
				[
					'id' => '',
					'val' => __('none', 'rrze-faq')
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
			'label' => __('Style', 'rrze-faq'),
			'type' => 'string'
		],
		'masonry' => [
			'field_type' => 'toggle',
			'label' => __('Grid', 'rrze-faq'),
			'type' => 'boolean',
			'default' => FALSE,
			'checked' => FALSE
		],
		'additional_class' => [
			'default' => '',
			'field_type' => 'text',
			'label' => __('Additonal CSS-class(es) for sourrounding DIV', 'rrze-faq'),
			'type' => 'text'
		],
		'lang' => [
			'default' => '',
			'field_type' => 'select',
			'label' => __('Language', 'rrze-faq'),
			'type' => 'string'
		],
		'sort' => [
			'values' => [
				[
					'id' => 'title',
					'val' => __('Title', 'rrze-faq')
				],
				[
					'id' => 'id',
					'val' => __('ID', 'rrze-faq')
				],
				[
					'id' => 'sortfield',
					'val' => __('Sort field', 'rrze-faq')
				],
			],
			'default' => 'title',
			'field_type' => 'select',
			'label' => __('Sort', 'rrze-faq'),
			'type' => 'string'
		],
		'order' => [
			'values' => [
				[
					'id' => 'ASC',
					'val' => __('ASC', 'rrze-faq')
				],
				[
					'id' => 'DESC',
					'val' => __('DESC', 'rrze-faq')
				],
			],
			'default' => 'ASC',
			'field_type' => 'select',
			'label' => __('Order', 'rrze-faq'),
			'type' => 'string'
		],
		'hstart' => [
			'default' => 2,
			'field_type' => 'text',
			'label' => __('Heading level of the first heading', 'rrze-faq'),
			'type' => 'number'
		],
	];

	$ret['lang']['values'] = [
		[
			'id' => '',
			'val' => __('All languages', 'rrze-faq')
		],
	];

	$langs = self::getConstants('langcodes');
	asort($langs);

	foreach ($langs as $short => $long) {
		$ret['lang']['values'][] =
			[
				'id' => $short,
				'val' => $long
			];
	}

	return $ret;

}

    public static function logIt(string $msg): void
    {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        $msg = wp_date("Y-m-d H:i:s") . ' | ' . $msg;

        if ($wp_filesystem->exists(FAQLOGFILE)) {
            $content = $wp_filesystem->get_contents(FAQLOGFILE);
            $content = $msg . "\n" . $content;
        } else {
            $content = $msg;
        }

        $wp_filesystem->put_contents(FAQLOGFILE, $content, FS_CHMOD_FILE);
    }

    public static function deleteLogfile(): void
    {
        if (file_exists(FAQLOGFILE)) {
            wp_delete_file(FAQLOGFILE);
        }
    }

    // Hinweis: getFields() und getShortcodeSettings() wären zu umfangreich für diese Darstellung,
    // sollten aber analog eingebaut und in überschaubare Teilmethoden ausgelagert werden.
}
