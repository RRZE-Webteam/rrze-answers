<?php

namespace RRZE\Answers;

use function __;

defined('ABSPATH') || exit;


// <?php

// namespace RRZE\Synonym\Config;

// defined('ABSPATH') || exit;

// /**
//  * Gibt der Name der Option zurück.
//  * @return array [description]
//  */
// function getOptionName() {
//     return 'rrze-synonym';
// }


// function getConstants() {
// 	$options = array(
// 		'fauthemes' => [
// 			'FAU-Einrichtungen',
// 			'FAU-Einrichtungen-BETA',
// 			'FAU-Medfak',
// 			'FAU-RWFak',
// 			'FAU-Philfak',
// 			'FAU-Techfak',
// 			'FAU-Natfak',
// 			'FAU-Blog',
// 			'FAU-Jobs'
// 		],
// 		'langcodes' => [
// 			"de" => __('German','rrze-synonym'),
// 			"en" => __('English','rrze-synonym'),
// 			"es" => __('Spanish','rrze-synonym'),
// 			"fr" => __('French','rrze-synonym'),
// 			"ru" => __('Russian','rrze-synonym'),
// 			"zh" => __('Chinese','rrze-synonym')
// 		]
// 	);               
// 	return $options;
// }

// /**
//  * Gibt die Einstellungen des Menus zurück.
//  * @return array [description]
//  */
// function getMenuSettings() {
//     return [
//         'page_title'    => __('RRZE Synonym', 'rrze-synonym'),
//         'menu_title'    => __('RRZE Synonym', 'rrze-synonym'),
//         'capability'    => 'manage_options',
//         'menu_slug'     => 'rrze-synonym',
//         'title'         => __('RRZE Synonym Settings', 'rrze-synonym'),
//     ];
// }

// /**
//  * Gibt die Einstellungen der Inhaltshilfe zurück.
//  * @return array [description]
//  */
// function getHelpTab() {
//     return [
//         [
//             'id'        => 'rrze-synonym-help',
//             'content'   => [
//                 '<p>' . __('Here comes the Context Help content.', 'rrze-synonym') . '</p>'
//             ],
//             'title'     => __('Overview', 'rrze-synonym'),
//             'sidebar'   => sprintf('<p><strong>%1$s:</strong></p><p><a href="https://blogs.fau.de/webworking">RRZE Webworking</a></p><p><a href="https://github.com/RRZE Webteam">%2$s</a></p>', __('For more information', 'rrze-synonym'), __('RRZE Webteam on Github', 'rrze-synonym'))
//         ]
//     ];
// }

// /**
//  * Gibt die Einstellungen der Optionsbereiche zurück.
//  * @return array [description]
//  */

// function getSections() {
// 	return [ 
// 		[
// 			'id'    => 'synonymdoms',
// 			'title' => __('Domains', 'rrze-synonym' )
// 		],
// 		[
// 			'id'    => 'synonymsync',
// 			'title' => __('Synchronize', 'rrze-synonym' )
// 		],
// 		[
// 		  	'id' => 'synonymlog',
// 		  	'title' => __('Logfile', 'rrze-synonym' )
// 		]
// 	];   
// }

// /**
//  * Gibt die Einstellungen der Optionsfelder zurück.
//  * @return array [description]
//  */

// function getFields() {
// 	return [
// 		'synonymdoms' => [
// 			[
// 				'name' => 'new_name',
// 				'label' => __('Short name', 'rrze-synonym' ),
// 				'desc' => __('Enter a short name for this domain.', 'rrze-synonym' ),
// 				'type' => 'text'
// 			],
// 			[
// 				'name' => 'new_url',
// 				'label' => __('URL', 'rrze-synonym' ),
// 				'desc' => __('Enter the domain\'s URL you want to receive synonyms from.', 'rrze-synonym' ),
// 				'type' => 'text'
// 			]
// 		],
// 		'synonymsync' => [
// 			[
// 				'name' => 'shortname',
// 				'label' => __('Short name', 'rrze-synonym' ),
// 				'desc' => __('Use this name as attribute \'domain\' in shortcode [synonym]', 'rrze-synonym' ),
// 				'type' => 'plaintext',
// 				'default' => ''
// 			],
// 			[
// 				'name' => 'url',
// 				'label' => __('URL', 'rrze-synonym' ),
// 				'desc' => '',
// 				'type' => 'plaintext',
// 				'default' => ''
// 			],
// 			[
// 				'name' => 'donotsync',
// 				'label' => __('Synchronize', 'rrze-synonym' ),
// 				'desc' => __('Do not synchronize', 'rrze-synonym' ),
// 				'type' => 'checkbox',
// 			],
// 			[
// 				'name' => 'hr',
// 				'label' => '',
// 				'desc' => '',
// 				'type' => 'line'
// 			],
// 			[
// 				'name' => 'info',
// 				'label' => __('Info', 'rrze-synonym' ),
// 				'desc' => __( 'All synonyms will be updated or inserted. Synonyms that have been deleted at the remote website will be deleted on this website, too.', 'rrze-synonym' ),
// 				'type' => 'plaintext',
// 				'default' => __( 'All synonyms will be updated or inserted. Synonyms that have been deleted at the remote website will be deleted on this website, too.', 'rrze-synonym' ),
// 			],
// 			[
// 				'name' => 'autosync',
// 				'label' => __('Mode', 'rrze-synonym' ),
// 				'desc' => __('Synchronize automatically', 'rrze-synonym' ),
// 				'type' => 'checkbox',
// 			],
// 			[
// 				'name' => 'frequency',
// 				'label' => __('Frequency', 'rrze-synonym' ),
// 				'desc' => '',
// 				'default' => 'daily',
// 				'options' => [
// 					'daily' => __('daily', 'rrze-synonym' ),
// 					'twicedaily' => __('twicedaily', 'rrze-synonym' )
// 				],
// 				'type' => 'select'
// 			],
// 		],		
//     	'synonymlog' => [
//         	[
//           		'name' => 'synonymlogfile',
//           		'type' => 'logfile',
//           		'default' => SYNONYMLOGFILE
//         	]
//       	]
// 	];
// }


// /**
//  * Gibt die Einstellungen der Parameter für Shortcode für den klassischen Editor und für Gutenberg zurück.
//  * @return array [description]
//  */

// function getShortcodeSettings(){
// 	return [
// 		'block' => [
//             'blocktype' => 'rrze-synonym/synonym',
// 			'blockname' => 'synonym',
// 			'title' => 'RRZE Synonym',
// 			'category' => 'widgets',
//             'icon' => 'translation',
//             'tinymce_icon' => 'translate',
// 		],
// 		'slug' => [
// 			'default' => '',
// 			'field_type' => 'text',
// 			'label' => __( 'Slug', 'rrze-synonym' ),
// 			'type' => 'text'
//         ],
// 		'id' => [
// 			'default' => 0,
// 			'field_type' => 'text',
// 			'label' => __( 'Synonym', 'rrze-synonym' ),
// 			'type' => 'number'
// 		],
// 		'gutenberg_shortcode_type' => [
// 			'values' => [
// 				'fau_abbr' => __( 'Abbreviation', 'rrze-synonym' ), // Abkürzung
// 				'synonym' => __( 'Longform', 'rrze-synonym' ) // Ausgeschriebene Form
// 			],
// 			'default' => 'synonym',
// 			'field_type' => 'radio',
// 			'label' => __( 'Type of output', 'rrze-synonym' ),
// 			'type' => 'string'
// 		],		
// 		// 'additional_class' => [
// 		// 	'default' => '',
// 		// 	'field_type' => 'text',
// 		// 	'label' => __( 'Additonal CSS-class(es) for surrounding DIV', 'rrze-synonym' ),
// 		// 	'type' => 'text'
// 		// ],
//     ];
// }

// function logIt( $msg ){
// 	date_default_timezone_set('Europe/Berlin');
// 	$msg = date("Y-m-d H:i:s") . ' | ' . $msg;
// 	if ( file_exists( SYNONYMLOGFILE ) ){
// 		$content = file_get_contents( SYNONYMLOGFILE );
// 		$content = $msg . "\n" . $content;
// 	}else {
// 		$content = $msg;
// 	}
// 	file_put_contents( SYNONYMLOGFILE, $content, LOCK_EX);
// }
  
// function deleteLogfile(){
// 	unlink( SYNONYMLOGFILE );
// }
  


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
                'rrze_faq' => 'rrze_faq',
                'rrze_category' => 'rrze_faq_category',
                'rrze_tag' => 'rrze_faq_tag'
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
			'default' => 'a-z',
			'field_type' => 'select',
			'label' => __('Glossary style', 'rrze-answers'),
			'type' => 'string'
		],
		'rrze_category' => [
			'default' => '',
			'field_type' => 'text',
			'label' => __('Categories', 'rrze-answers'),
			'type' => 'text'
		],
		'rrze_tag' => [
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
			'label' => __('rrze_faq', 'rrze-answers'),
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
