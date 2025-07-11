<?php

namespace RRZE\Answers;

use function __;

defined('ABSPATH') || exit;

class Config
{
    public static function getOptionName(): string
    {
        return 'rrze-answers';
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
                'de' => __('German', 'rrze-answers'),
                'en' => __('English', 'rrze-answers'),
                'es' => __('Spanish', 'rrze-answers'),
                'fr' => __('French', 'rrze-answers'),
                'ru' => __('Russian', 'rrze-answers'),
                'zh' => __('Chinese', 'rrze-answers')
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
            'page_title' => __('RRZE FAQ', 'rrze-answers'),
            'menu_title' => __('RRZE FAQ', 'rrze-answers'),
            'capability' => 'manage_options',
            'menu_slug' => 'rrze-answers',
            'title' => __('RRZE FAQ Settings', 'rrze-answers'),
        ];
    }

    public static function getHelpTab(): array
    {
        return [[
            'id' => 'rrze-answers-help',
            'content' => ['<p>' . __('Here comes the Context Help content.', 'rrze-answers') . '</p>'],
            'title' => __('Overview', 'rrze-answers'),
            'sidebar' => sprintf(
                '<p><strong>%1$s:</strong></p><p><a href="https://blogs.fau.de/webworking">RRZE Webworking</a></p><p><a href="https://github.com/RRZE Webteam">%2$s</a></p>',
                __('For more information', 'rrze-answers'),
                __('RRZE Webteam on Github', 'rrze-answers')
            )
        ]];
    }



/**
 * Gibt die Einstellungen der Parameter für Shortcode für den klassischen Editor und für Gutenberg zurück.
 * @return array [description]
 */

public static function getShortcodeSettings():array
{
	$ret = [
		'block' => [
			'blocktype' => 'rrze-answers/faq',
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
			'default' => 'a-z',
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
			'label' => __('FAQ', 'rrze-answers'),
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

	$ret['lang']['values'] = [
		[
			'id' => '',
			'val' => __('All languages', 'rrze-answers')
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

        if ($wp_filesystem->exists(ANSWERSLOGFILE)) {
            $content = $wp_filesystem->get_contents(ANSWERSLOGFILE);
            $content = $msg . "\n" . $content;
        } else {
            $content = $msg;
        }

        $wp_filesystem->put_contents(ANSWERSLOGFILE, $content, FS_CHMOD_FILE);
    }

    public static function deleteLogfile(): void
    {
        if (file_exists(ANSWERSLOGFILE)) {
            wp_delete_file(ANSWERSLOGFILE);
        }
    }

    // Hinweis: getFields() und getShortcodeSettings() wären zu umfangreich für diese Darstellung,
    // sollten aber analog eingebaut und in überschaubare Teilmethoden ausgelagert werden.
}
