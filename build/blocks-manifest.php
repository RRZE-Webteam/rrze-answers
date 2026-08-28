<?php
// This file is generated. Do not modify it manually.
return array(
	'faq' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'rrze-answers/faq',
		'version' => '5.3.4',
		'title' => 'RRZE FAQ',
		'category' => 'rrze',
		'description' => 'Display FAQ',
		'icon' => 'editor-help',
		'example' => array(
			
		),
		'attributes' => array(
			'glossary' => array(
				'type' => 'string'
			),
			'glossarystyle' => array(
				'type' => 'string'
			),
			'category' => array(
				'type' => 'string'
			),
			'tag' => array(
				'type' => 'string'
			),
			'id' => array(
				'type' => 'string'
			),
			'hide_accordion' => array(
				'type' => 'boolean'
			),
			'hide_title' => array(
				'type' => 'boolean'
			),
			'masonry' => array(
				'type' => 'boolean'
			),
			'search' => array(
				'type' => 'boolean',
				'default' => false
			),
			'color' => array(
				'type' => 'string'
			),
			'style' => array(
				'type' => 'string',
				'default' => 'light'
			),
			'additional_class' => array(
				'type' => 'string'
			),
			'lang' => array(
				'type' => 'string'
			),
			'sort' => array(
				'type' => 'string'
			),
			'order' => array(
				'type' => 'string'
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
		'viewScript' => array(
			'rrze-answers-accordion',
			'rrze-answers-search'
		),
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
				'type' => 'integer',
				'default' => 0
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
		'version' => '2.1.11',
		'title' => 'RRZE Glossary',
		'category' => 'rrze',
		'description' => 'Display glossaries',
		'icon' => 'book',
		'example' => array(
			
		),
		'attributes' => array(
			'category' => array(
				'type' => 'string'
			),
			'tag' => array(
				'type' => 'string'
			),
			'id' => array(
				'type' => 'string'
			),
			'register' => array(
				'type' => 'string'
			),
			'registerstyle' => array(
				'type' => 'string'
			),
			'hide_accordion' => array(
				'type' => 'boolean'
			),
			'hide_title' => array(
				'type' => 'boolean'
			),
			'masonry' => array(
				'type' => 'boolean'
			),
			'search' => array(
				'type' => 'boolean',
				'default' => false
			),
			'expand_all_link' => array(
				'type' => 'boolean'
			),
			'load_open' => array(
				'type' => 'boolean'
			),
			'color' => array(
				'type' => 'string'
			),
			'style' => array(
				'type' => 'string',
				'default' => 'light'
			),
			'additional_class' => array(
				'type' => 'string'
			),
			'lang' => array(
				'type' => 'string'
			),
			'sort' => array(
				'type' => 'string'
			),
			'order' => array(
				'type' => 'string'
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
		'viewScript' => array(
			'rrze-answers-accordion',
			'rrze-answers-search'
		),
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
		'version' => '3.0.4',
		'title' => 'RRZE Placeholder',
		'category' => 'rrze',
		'description' => 'Display placeholders',
		'icon' => 'editor-paste-text',
		'example' => array(
			
		),
		'attributes' => array(
			'id' => array(
				'type' => 'string'
			),
			'additional_class' => array(
				'type' => 'string'
			),
			'lang' => array(
				'type' => 'string'
			),
			'sort' => array(
				'type' => 'string'
			),
			'order' => array(
				'type' => 'string'
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
		'version' => '3.0.4',
		'title' => 'RRZE Synonym',
		'category' => 'rrze',
		'description' => 'Display synonyms',
		'icon' => 'translation',
		'example' => array(
			
		),
		'attributes' => array(
			'register' => array(
				'type' => 'string'
			),
			'registerstyle' => array(
				'type' => 'string'
			),
			'category' => array(
				'type' => 'string'
			),
			'tag' => array(
				'type' => 'string'
			),
			'id' => array(
				'type' => 'string'
			),
			'hide_accordion' => array(
				'type' => 'boolean'
			),
			'hide_title' => array(
				'type' => 'boolean'
			),
			'expand_all_link' => array(
				'type' => 'boolean'
			),
			'load_open' => array(
				'type' => 'boolean'
			),
			'color' => array(
				'type' => 'string'
			),
			'additional_class' => array(
				'type' => 'string'
			),
			'lang' => array(
				'type' => 'string'
			),
			'sort' => array(
				'type' => 'string'
			),
			'order' => array(
				'type' => 'string'
			),
			'hstart' => array(
				'type' => 'number'
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
