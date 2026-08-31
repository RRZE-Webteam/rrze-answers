<?php
// This file is generated. Do not modify it manually.
return array(
	'faq' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'rrze-answers/faq',
		'version' => '6.0.0',
		'title' => 'RRZE FAQ',
		'category' => 'rrze',
		'description' => 'Display FAQ',
		'icon' => 'editor-help',
		'example' => array(

		),
		'attributes' => array(
			'glossary' => array(
				'type' => 'string',
				'default' => ''
			),
			'glossarystyle' => array(
				'type' => 'string',
				'default' => ''
			),
			'category' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'string'
				),
				'default' => array(

				)
			),
			'tag' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'string'
				),
				'default' => array(

				)
			),
			'id' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'integer'
				),
				'default' => array(

				)
			),
			'hide_accordion' => array(
				'type' => 'boolean',
				'default' => false
			),
			'hide_title' => array(
				'type' => 'boolean',
				'default' => false
			),
			'masonry' => array(
				'type' => 'boolean',
				'default' => false
			),
			'search' => array(
				'type' => 'boolean',
				'default' => false
			),
			'color' => array(
				'type' => 'string',
				'default' => ''
			),
			'style' => array(
				'type' => 'string',
				'default' => 'light'
			),
			'additional_class' => array(
				'type' => 'string',
				'default' => ''
			),
			'lang' => array(
				'type' => 'string',
				'default' => ''
			),
			'sort' => array(
				'type' => 'string',
				'default' => 'title'
			),
			'order' => array(
				'type' => 'string',
				'default' => 'ASC'
			),
			'hstart' => array(
				'type' => 'number',
				'default' => 2
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'rrze-answers',
		'editorScript' => array(
			'file:./index.js',
			'rrze-answers-accordion',
			'rrze-answers-search'
		),
		'editorStyle' => 'rrze-answers-css',
		'render' => 'file:./render.php'
	),
	'faq-widget' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'rrze-answers/faq-widget',
		'version' => '2.0.0',
		'title' => 'RRZE FAQ Widget',
		'category' => 'widgets',
		'icon' => 'editor-help',
		'description' => 'Displays a random FAQ from a category.',
		'attributes' => array(
			'id' => array(
				'type' => 'integer',
				'default' => 0
			),
			'catID' => array(
				'type' => 'integer',
				'default' => 0
			),
			'hide_title' => array(
				'type' => 'boolean',
				'default' => false
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'rrze-answers',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'rrze-answers-css',
		'render' => 'file:./render.php'
	),
	'glossary' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'rrze-answers/glossary',
		'version' => '3.0.0',
		'title' => 'RRZE Glossary',
		'category' => 'rrze',
		'description' => 'Display glossaries',
		'icon' => 'book',
		'example' => array(

		),
		'attributes' => array(
			'category' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'string'
				),
				'default' => array(

				)
			),
			'tag' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'string'
				),
				'default' => array(

				)
			),
			'id' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'integer'
				),
				'default' => array(

				)
			),
			'register' => array(
				'type' => 'string',
				'default' => ''
			),
			'registerstyle' => array(
				'type' => 'string',
				'default' => ''
			),
			'hide_accordion' => array(
				'type' => 'boolean',
				'default' => false
			),
			'hide_title' => array(
				'type' => 'boolean',
				'default' => false
			),
			'masonry' => array(
				'type' => 'boolean',
				'default' => false
			),
			'search' => array(
				'type' => 'boolean',
				'default' => false
			),
			'expand_all_link' => array(
				'type' => 'boolean',
				'default' => false
			),
			'load_open' => array(
				'type' => 'boolean',
				'default' => false
			),
			'color' => array(
				'type' => 'string',
				'default' => ''
			),
			'style' => array(
				'type' => 'string',
				'default' => 'light'
			),
			'additional_class' => array(
				'type' => 'string',
				'default' => ''
			),
			'lang' => array(
				'type' => 'string',
				'default' => ''
			),
			'sort' => array(
				'type' => 'string',
				'default' => 'title'
			),
			'order' => array(
				'type' => 'string',
				'default' => 'ASC'
			),
			'hstart' => array(
				'type' => 'number',
				'default' => 2
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'rrze-answers',
		'editorScript' => array(
			'file:./index.js',
			'rrze-answers-accordion',
			'rrze-answers-search'
		),
		'render' => 'file:./render.php'
	),
	'placeholder' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'rrze-answers/placeholder',
		'version' => '4.0.0',
		'title' => 'RRZE Placeholder',
		'category' => 'rrze',
		'description' => 'Display placeholders',
		'icon' => 'editor-paste-text',
		'example' => array(

		),
		'attributes' => array(
			'id' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'integer'
				),
				'default' => array(

				)
			),
			'additional_class' => array(
				'type' => 'string',
				'default' => ''
			),
			'lang' => array(
				'type' => 'string',
				'default' => ''
			),
			'sort' => array(
				'type' => 'string',
				'default' => 'title'
			),
			'order' => array(
				'type' => 'string',
				'default' => 'ASC'
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'rrze-answers',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'rrze-answers-admin-css',
		'render' => 'file:./render.php'
	),
	'synonym' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'rrze-answers/synonym',
		'version' => '4.0.0',
		'title' => 'RRZE Synonym',
		'category' => 'rrze',
		'description' => 'Display synonyms',
		'icon' => 'translation',
		'example' => array(

		),
		'attributes' => array(
			'category' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'string'
				),
				'default' => array(

				)
			),
			'tag' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'string'
				),
				'default' => array(

				)
			),
			'id' => array(
				'type' => 'array',
				'items' => array(
					'type' => 'integer'
				),
				'default' => array(

				)
			),
			'hide_accordion' => array(
				'type' => 'boolean',
				'default' => false
			),
			'hide_title' => array(
				'type' => 'boolean',
				'default' => false
			),
			'expand_all_link' => array(
				'type' => 'boolean',
				'default' => false
			),
			'load_open' => array(
				'type' => 'boolean',
				'default' => false
			),
			'color' => array(
				'type' => 'string',
				'default' => ''
			),
			'additional_class' => array(
				'type' => 'string',
				'default' => ''
			),
			'lang' => array(
				'type' => 'string',
				'default' => ''
			),
			'sort' => array(
				'type' => 'string',
				'default' => 'title'
			),
			'order' => array(
				'type' => 'string',
				'default' => 'ASC'
			),
			'hstart' => array(
				'type' => 'number',
				'default' => 2
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'rrze-answers',
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	)
);
