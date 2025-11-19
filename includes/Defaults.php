<?php

namespace RRZE\Answers;

use RRZE\Answers\Common\API\SyncAPI;
use function RRZE\Answers\plugin;
use RRZE\Answers\Common\Tools;


defined('ABSPATH') || exit;

define('ENDPOINT', 'wp-json/wp/v2/');

define('RRZEANSWERSLOGFILE', 'rrze-answers.log');

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
        $pagelist = Tools::getPageList();

        $defaults = [
            'settings' => [
                'option_name' => 'rrze-answers',
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
                ['id' => 'domains', 'title' => __('Domains', 'rrze-answers')],
                ['id' => 'import', 'title' => __('Import', 'rrze-answers')],
                ['id' => 'faqlog', 'title' => __('Logfile', 'rrze-answers')]
            ],
            'fields' => [
                'permissions' => [
                    [
                        'name' => 'api_active_rrze_faq',
                        'label' => __('Allow to import FAQ', 'rrze-answers'),
                        'description' => __('Allow other websites to import your FAQ. Your SEO will not be affected. Structured data is used for your content only.', 'rrze-answers'),
                        'type' => 'checkbox',
                    ],
                    [
                        'name' => 'api_active_rrze_glossary',
                        'label' => __('Allow to import glossary', 'rrze-answers'),
                        'description' => __('Allow other websites to import your glossary. Your SEO will not be affected. Structured data is used for your content only.', 'rrze-answers'),
                        'type' => 'checkbox',
                    ]
                ],
                'domains' => [
                    [
                        'name' => 'domains',
                        'label' => __('Domains', 'rrze-answers'),
                        'desc' => __('Enter the domain\'s URL you want to receive FAQ from.', 'rrze-answers'),
                        'type' => 'domains-table'
                    ],
                    [
                        'name' => 'new_url',
                        'label' => __('New Domain', 'rrze-answers'),
                        'desc' => __('Enter the domain\'s URL you want to receive FAQ from.', 'rrze-answers'),
                        'type' => 'text',
                        'default' => 'https://',
                    ]
                ],
                'permalink_settings' => [
                    [
                        'name' => 'label_faq',
                        'label' => __('FAQ', 'rrze-answers'),
                        'type' => 'hr',
                    ],
                    [
                        'name' => 'redirect_archivpage_uri_faq',
                        'label' => __('Archive page', 'rrze-answers'),
                        'description' => '',
                        'type' => 'select',
                        'options' => $pagelist,
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
                        'label' => __('Category Slug', 'rrze-answers'),
                        'description' => '',
                        'type' => 'text',
                        'default' => 'faq_category',
                        'placeholder' => 'faq_category'

                    ],
                    [
                        'name' => 'custom_faq_tag_slug',
                        'label' => __('Tag Slug', 'rrze-answers'),
                        'description' => '',
                        'type' => 'text',
                        'default' => 'faq_tag',
                        'placeholder' => 'faq_tag'
                    ],
                    [
                        'name' => 'label_glossary',
                        'label' => __('Glossary', 'rrze-answers'),
                        'type' => 'hr',
                    ],
                    [
                        'name' => 'redirect_archivpage_uri_glossary',
                        'label' => __('Archive page', 'rrze-answers'),
                        'description' => '',
                        'type' => 'select',
                        'options' => $pagelist,
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
                        'label' => __('Category Slug', 'rrze-answers'),
                        'description' => '',
                        'type' => 'text',
                        'default' => 'glossary_category',
                        'placeholder' => 'glossary_category'

                    ],
                    [
                        'name' => 'custom_glossary_tag_slug',
                        'label' => __('Tag Slug', 'rrze-answers'),
                        'description' => '',
                        'type' => 'text',
                        'default' => 'glossary_tag',
                        'placeholder' => 'glossary_tag'
                    ],
                    [
                        'name' => 'label_placeholder',
                        'label' => __('Placeholder', 'rrze-answers'),
                        'type' => 'hr',
                    ],
                    [
                        'name' => 'redirect_archivpage_uri_placeholder',
                        'label' => __('Archive page', 'rrze-answers'),
                        'description' => '',
                        'type' => 'select',
                        'options' => $pagelist,
                        'default' => ''
                    ],
                    [
                        'name' => 'custom_placeholder_slug',
                        'label' => __('Placeholder Slug', 'rrze-answers'),
                        'description' => '',
                        'type' => 'text',
                        'default' => 'rrze_placeholder',
                        'placeholder' => 'rrze_placeholder'
                    ],
                ],
                'import' => [],
                'faqlog' => [
                    [
                        'name' => 'ANSWERSLOGFILE',
                        'type' => 'logfile',
                        'default' => ANSWERSLOGFILE
                    ]
                ]
            ],
            'lang' => [
                '' => __('All languages', 'rrze-answers'),
                'de' => __('German', 'rrze-answers'),
                'en' => __('English', 'rrze-answers'),
                'es' => __('Spanish', 'rrze-answers'),
                'fr' => __('French', 'rrze-answers'),
                'ru' => __('Russian', 'rrze-answers'),
                'zh' => __('Chinese', 'rrze-answers')
            ],
        ];

        $syncAPI = new SyncAPI();
        $domains = $syncAPI->getDomains();

        foreach ($domains as $identifier => $url) {
            $defaults['fields']['import'][] = [
                'name' => 'hr_' . $identifier,
                'label' => $identifier . ' (' . $url . ')',
                'type' => 'hr',
            ];

            $types = [
                'faq' => 'FAQ',
                'glossary' => __('Glossary', 'rrze-answers')
            ];

            $filter = '';

            foreach ($types as $type => $label) {
                $cats = $syncAPI->getTaxonomies($url, 'rrze_' . $type . '_category', $filter);
                $options = [];

                foreach ($cats as $key => $unused) {
                    $options[$key] = $key;
                }

                if (!empty($cats)) {
                    $defaults['fields']['import'][] = [
                        'name' => $type . '_categories_' . $identifier,
                        'label' => $label . ' ' . __('Categories', 'rrze-answers'),
                        'description' => __('Please select the categories you\'d like to fetch ' . $label . ' to.', 'rrze-answers'),
                        'type' => 'select-multiple',
                        'options' => $options
                    ];
                } else {
                    $defaults['fields']['import'][] = [
                        'name' => $type . '_categories_' . $identifier,
                        'label' => $label . ' ' . __('Categories', 'rrze-answers'),
                        'description' => __('Please select the categories you\'d like to fetch ' . $label . ' to.', 'rrze-answers'),
                        'type' => 'msg',
                        'placeholder' => __('Category not found.', 'rrze-answers')
                    ];
                }
            }
        }

        $defaults['fields']['import'][] = [
            'name' => 'hr_only',
            'label' => '',
            'type' => 'hr',
        ];

        $defaults['fields']['import'][] = [
            'name' => 'frequency',
            'label' => __('Synchronize automatically', 'rrze-answers'),
            'description' => '',
            'default' => '',
            'options' => [
                '' => __('-- off --', 'rrze-answers'),
                'daily' => __('daily', 'rrze-answers'),
                'twicedaily' => __('twicedaily', 'rrze-answers')
            ],
            'type' => 'select'
        ];

        return apply_filters('rrze-answers_defaults', $defaults);
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
