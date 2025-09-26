<?php

namespace RRZE\Answers;

use function RRZE\Answers\plugin;
use RRZE\Answers\Common\Tools;


defined('ABSPATH') || exit;

/**
 * Class Defaults
 *
 * Holds and provides access to plugin-wide default values.
 *
 * @package RRZE\Answers\Common
 */
class Defaults
{
    /**
     * Plugin default values.
     *
     * @var array
     */
    private readonly array $defaults;

    /**
     * Defaults constructor.
     * 
     * @return void
     */
    public function __construct()
    {
        $this->defaults = $this->load();
    }

    /**
     * Returns the default values, filtered via WordPress.
     *
     * @return array
     */
    private function load(): array
    {
        return apply_filters(
            'rrze_answers_defaults',
            [
                'settings' => [
                    'option_name' => 'rrze_answers_settings',
                    'menu_title' => __('RRZE Answers', 'rrze-answers'),
                    'page_title' => __('RRZE Answers Settings', 'rrze-answers'),
                    'capability' => 'manage_options',
                    'checkbox_option' => false,
                    'text_placeholder' => __('Enter your text here...', 'rrze-answers'),
                    'select_default' => 'none',
                ],
                'sections' => [
                    ['id' => 'permissions', 'title' => __('Permissions', 'rrze-answers')],
                    ['id' => 'permalink_settings', 'title' => __('Permalink Settings', 'rrze-answers')],
                    ['id' => 'import_faq', 'title' => __('Import FAQ', 'rrze-answers')],
                    ['id' => 'import_glossary', 'title' => __('Import Glossary', 'rrze-answers')],
                    ['id' => 'import_synonym', 'title' => __('Import Synonym', 'rrze-answers')],
                    ['id' => 'faqlog', 'title' => __('Logfile', 'rrze-answers')]
                ],
                'fields' => [
                    'permissions' => [
                        [
                            'name' => 'api_active_faq',
                            'label' => __('Allow to import FAQ', 'rrze-answers'),
                            'description' => __('Allow other websites to import your FAQ. Your SEO will not be affected. Structured data is used for your content only.', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'api_active_glossary',
                            'label' => __('Allow to import glossary', 'rrze-answers'),
                            'description' => __('Allow other websites to import your glossary. Your SEO will not be affected. Structured data is used for your content only.', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'api_active_synonym',
                            'label' => __('Allow to import synonym', 'rrze-answers'),
                            'description' => __('Allow other websites to import your synonym. Your SEO will not be affected. Structured data is used for your content only.', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                    ],                    
                    'import_faq' => [
                        [
                            'name' => 'remote_api_url',
                            'label' => __('Remote site', 'rrze-answers'),
                            'description' => __('Select the site you want to synchronize with.', 'rrze-answers'),
                            'type' => 'select',
                            'options' => Tools::getSitesWithPlugin(),
                            'default' => ''
                        ],
                        [
                            'name' => 'shortname',
                            'label' => __('Short name', 'rrze-answers'),
                            'description' => __('Use this name as attribute \'domain\' in shortcode [faq]', 'rrze-answers'),
                            'type' => 'plaintext',
                            'default' => ''
                        ],
                        [
                            'name' => 'url',
                            'label' => __('URL', 'rrze-answers'),
                            'description' => '',
                            'type' => 'plaintext',
                            'default' => ''
                        ],
                        [
                            'name' => 'categories',
                            'label' => __('Categories', 'rrze-answers'),
                            'description' => __('Please select the categories you\'d like to fetch FAQ to.', 'rrze-answers'),
                            'type' => 'multiselect',
                            'options' => []
                        ],
                        [
                            'name' => 'donotsync',
                            'label' => __('Synchronize', 'rrze-answers'),
                            'description' => __('Do not synchronize', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'hr',
                            'label' => '',
                            'description' => '',
                            'type' => 'line'
                        ],
                        [
                            'name' => 'info',
                            'label' => __('Info', 'rrze-answers'),
                            'description' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                            'type' => 'plaintext',
                            'default' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                        ],
                        [
                            'name' => 'autosync',
                            'label' => __('Mode', 'rrze-answers'),
                            'description' => __('Synchronize automatically', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'frequency',
                            'label' => __('Frequency', 'rrze-answers'),
                            'description' => '',
                            'default' => 'daily',
                            'options' => [
                                'daily' => __('daily', 'rrze-answers'),
                                'twicedaily' => __('twicedaily', 'rrze-answers')
                            ],
                            'type' => 'select'
                        ],
                    ],
                    'import_glossary' => [
                        [
                            'name' => 'remote_api_url',
                            'label' => __('Remote site', 'rrze-answers'),
                            'description' => __('Select the site you want to synchronize with.', 'rrze-answers'),
                            'type' => 'select',
                            'options' => Tools::getSitesWithPlugin(),
                            'default' => ''
                        ],
                        [
                            'name' => 'shortname',
                            'label' => __('Short name', 'rrze-answers'),
                            'description' => __('Use this name as attribute \'domain\' in shortcode [faq]', 'rrze-answers'),
                            'type' => 'plaintext',
                            'default' => ''
                        ],
                        [
                            'name' => 'url',
                            'label' => __('URL', 'rrze-answers'),
                            'description' => '',
                            'type' => 'plaintext',
                            'default' => ''
                        ],
                        [
                            'name' => 'categories',
                            'label' => __('Categories', 'rrze-answers'),
                            'description' => __('Please select the categories you\'d like to fetch FAQ to.', 'rrze-answers'),
                            'type' => 'multiselect',
                            'options' => []
                        ],
                        [
                            'name' => 'donotsync',
                            'label' => __('Synchronize', 'rrze-answers'),
                            'description' => __('Do not synchronize', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'hr',
                            'label' => '',
                            'description' => '',
                            'type' => 'line'
                        ],
                        [
                            'name' => 'info',
                            'label' => __('Info', 'rrze-answers'),
                            'description' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                            'type' => 'plaintext',
                            'default' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                        ],
                        [
                            'name' => 'autosync',
                            'label' => __('Mode', 'rrze-answers'),
                            'description' => __('Synchronize automatically', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'frequency',
                            'label' => __('Frequency', 'rrze-answers'),
                            'description' => '',
                            'default' => 'daily',
                            'options' => [
                                'daily' => __('daily', 'rrze-answers'),
                                'twicedaily' => __('twicedaily', 'rrze-answers')
                            ],
                            'type' => 'select'
                        ],
                    ],
                    'import_synonym' => [
                        [
                            'name' => 'remote_api_url',
                            'label' => __('Remote site', 'rrze-answers'),
                            'description' => __('Select the site you want to synchronize with.', 'rrze-answers'),
                            'type' => 'select',
                            'options' => Tools::getSitesWithPlugin(),
                            'default' => ''
                        ],
                        [
                            'name' => 'shortname',
                            'label' => __('Short name', 'rrze-answers'),
                            'description' => __('Use this name as attribute \'domain\' in shortcode [faq]', 'rrze-answers'),
                            'type' => 'plaintext',
                            'default' => ''
                        ],
                        [
                            'name' => 'url',
                            'label' => __('URL', 'rrze-answers'),
                            'description' => '',
                            'type' => 'plaintext',
                            'default' => ''
                        ],
                        [
                            'name' => 'categories',
                            'label' => __('Categories', 'rrze-answers'),
                            'description' => __('Please select the categories you\'d like to fetch FAQ to.', 'rrze-answers'),
                            'type' => 'multiselect',
                            'options' => []
                        ],
                        [
                            'name' => 'donotsync',
                            'label' => __('Synchronize', 'rrze-answers'),
                            'description' => __('Do not synchronize', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'hr',
                            'label' => '',
                            'description' => '',
                            'type' => 'line'
                        ],
                        [
                            'name' => 'info',
                            'label' => __('Info', 'rrze-answers'),
                            'description' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                            'type' => 'plaintext',
                            'default' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                        ],
                        [
                            'name' => 'autosync',
                            'label' => __('Mode', 'rrze-answers'),
                            'description' => __('Synchronize automatically', 'rrze-answers'),
                            'type' => 'checkbox',
                        ],
                        [
                            'name' => 'frequency',
                            'label' => __('Frequency', 'rrze-answers'),
                            'description' => '',
                            'default' => 'daily',
                            'options' => [
                                'daily' => __('daily', 'rrze-answers'),
                                'twicedaily' => __('twicedaily', 'rrze-answers')
                            ],
                            'type' => 'select'
                        ],
                    ],
                    'permalink_settings' => [
                        [
                            'name' => 'redirect_archivpage_uri_faq',
                            'label' => __('Archive page', 'rrze-answers'),
                            'description' => '',
                            'type' => 'select',
                            'options' => Tools::getPageList(),
                            'default' => ''
                        ],
                        [
                            'name' => 'custom_faq_slug',
                            'label' => __('FAQ Slug', 'rrze-answers'),
                            'description' => '',
                            'type' => 'text',
                            'default' => 'rrze_faq',
                            'placeholder' => 'rrze_faq'
                        ],
                        [
                            'name' => 'custom_faq_category_slug',
                            'label' => __('FAQ Category Slug', 'rrze-answers'),
                            'description' => '',
                            'type' => 'text',
                            'default' => 'faq_category',
                            'placeholder' => 'faq_category'

                        ],
                        [
                            'name' => 'custom_faq_tag_slug',
                            'label' => __('FAQ Tag Slug', 'rrze-answers'),
                            'description' => '',
                            'type' => 'text',
                            'default' => 'faq_tag',
                            'placeholder' => 'faq_tag'
                        ],
                        [
                            'name' => 'redirect_archivpage_uri_glossary',
                            'label' => __('Archive page', 'rrze-answers'),
                            'description' => '',
                            'type' => 'select',
                            'options' => Tools::getPageList(),
                            'default' => ''
                        ],
                        [
                            'name' => 'custom_glossary_slug',
                            'label' => __('Glossary Slug', 'rrze-answers'),
                            'description' => '',
                            'type' => 'text',
                            'default' => 'rrze_glossary',
                            'placeholder' => 'rrze_glossary'
                        ],
                        [
                            'name' => 'custom_glossary_category_slug',
                            'label' => __('Glossary Category Slug', 'rrze-answers'),
                            'description' => '',
                            'type' => 'text',
                            'default' => 'glossary_category',
                            'placeholder' => 'glossary_category'

                        ],
                        [
                            'name' => 'custom_glossary_tag_slug',
                            'label' => __('Glossary Tag Slug', 'rrze-answers'),
                            'description' => '',
                            'type' => 'text',
                            'default' => 'glossary_tag',
                            'placeholder' => 'glossary_tag'
                        ],
                        [
                            'name' => 'redirect_archivpage_uri_synonym',
                            'label' => __('Archive page', 'rrze-answers'),
                            'description' => '',
                            'type' => 'select',
                            'options' => Tools::getPageList(),
                            'default' => ''
                        ],
                        [
                            'name' => 'custom_synonym_slug',
                            'label' => __('Synonym Slug', 'rrze-answers'),
                            'description' => '',
                            'type' => 'text',
                            'default' => 'rrze_synonym',
                            'placeholder' => 'rrze_synonym'
                        ],
                    ],
                    'faqlog' => [
                        [
                            'name' => 'ANSWERSLOGFILE',
                            'type' => 'logfile',
                            'default' => ANSWERSLOGFILE
                        ]
                    ]
                ],
                'lang' => [
                    '' =>  __('All languages', 'rrze-answers'),
                    'de'  => __('German', 'rrze-answers'),
                    'en' => __('English', 'rrze-answers'),
                    'es' => __('Spanish', 'rrze-answers'),
                    'fr' => __('French', 'rrze-answers'),
                    'ru' => __('Russian', 'rrze-answers'),
                    'zh' => __('Chinese', 'rrze-answers')
                    ],

                // 'lang' => [
                //     // [
                //     //     'id' => '',
                //     //     'val' => __('All languages', 'rrze-answers')
                //     // ],
                //     [
                //         'id' => 'de',
                //         'val' => __('German', 'rrze-answers'),
                //     ],
                //     [
                //         'id' => 'en',
                //         'val' => __('English', 'rrze-answers'),
                //     ],
                //     [
                //         'id' => 'es',
                //         'val' => __('Spanish', 'rrze-answers'),
                //     ],
                //     [
                //         'id' => 'fr',
                //         'val' => __('French', 'rrze-answers'),
                //     ],
                //     [
                //         'id' => 'ru',
                //         'val' => __('Russian', 'rrze-answers'),
                //     ],
                //     [
                //         'id' => 'zh',
                //         'val' => __('Chinese', 'rrze-answers')
                //     ],
                // ],
            ]
        );
    }

    /**
     * Retrieve a default value by key.
     *
     * @param string $key The key of the default.
     * @return mixed|null The value if found, or null.
     */
    public function get(string $key): mixed
    {
        return $this->defaults[$key] ?? null;
    }

    /**
     * Get all defaults.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->defaults;
    }

    /**
     * Prepends a deterministic, 6-char unique prefix to any key.
     *
     * @param string $key The raw key to namespace.
     * @return string The 6-char-prefixed key.
     */
    public function withPrefix(string $key = ''): string
    {
        $rawSlug = plugin()->getSlug();
        $clean = preg_replace('/[^a-z0-9]/', '', $rawSlug);

        $keep = min(3, strlen($clean));
        $part = substr($clean, 0, $keep);

        $needed = 6 - strlen($part);
        $hash = substr(md5($clean), 0, $needed);

        $prefix = $part . $hash;

        if (!preg_match('/^[a-z]/', $prefix)) {
            $prefix = 'p' . substr($prefix, 0, 5);
        }

        return $prefix . '_' . sanitize_key($key);
    }
}
