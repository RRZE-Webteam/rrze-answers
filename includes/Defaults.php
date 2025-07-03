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
        return apply_filters('rrze_answers_defaults', [
            'cpt' => [
                'name' => $this->withPrefix('book'),
                'taxonomy_name' => $this->withPrefix('genre'),
            ],
            'settings' => [
                'option_name' => 'rrze_answers_settings',
                'menu_title' => __('Answers', 'rrze-answers'),
                'page_title' => __('RRZE Answers Settings', 'rrze-answers'),
                'capability' => 'manage_options',
                'checkbox_option' => false,
                'text_placeholder' => __('Enter your text here...', 'rrze-answers'),
                'select_default' => 'none',
            ],
            'sections' => [
                ['id' => 'doms', 'title' => __('Domains', 'rrze-answers')],
                ['id' => 'faqsync', 'title' => __('Synchronize', 'rrze-answers')],
                ['id' => 'website', 'title' => __('Website', 'rrze-answers')],
                ['id' => 'faqlog', 'title' => __('Logfile', 'rrze-answers')]
            ],
            'fields' => [
                'doms' => [
                    [
                        'name' => 'new_name',
                        'label' => __('Short name', 'rrze-answers'),
                        'desc' => __('Enter a short name for this domain.', 'rrze-answers'),
                        'type' => 'text'
                    ],
                    [
                        'name' => 'new_url',
                        'label' => __('URL', 'rrze-answers'),
                        'desc' => __('Enter the domain\'s URL you want to receive FAQ from.', 'rrze-answers'),
                        'type' => 'text'
                    ]
                ],
                'faqsync' => [
                    [
                        'name' => 'shortname',
                        'label' => __('Short name', 'rrze-answers'),
                        'desc' => __('Use this name as attribute \'domain\' in shortcode [faq]', 'rrze-answers'),
                        'type' => 'plaintext',
                        'default' => ''
                    ],
                    [
                        'name' => 'url',
                        'label' => __('URL', 'rrze-answers'),
                        'desc' => '',
                        'type' => 'plaintext',
                        'default' => ''
                    ],
                    [
                        'name' => 'categories',
                        'label' => __('Categories', 'rrze-answers'),
                        'desc' => __('Please select the categories you\'d like to fetch FAQ to.', 'rrze-answers'),
                        'type' => 'multiselect',
                        'options' => []
                    ],
                    [
                        'name' => 'donotsync',
                        'label' => __('Synchronize', 'rrze-answers'),
                        'desc' => __('Do not synchronize', 'rrze-answers'),
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
                        'label' => __('Info', 'rrze-answers'),
                        'desc' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                        'type' => 'plaintext',
                        'default' => __('All FAQ that match to the selected categories will be updated or inserted. Already synchronized FAQ that refer to categories which are not selected will be deleted. FAQ that have been deleted at the remote website will be deleted on this website, too.', 'rrze-answers'),
                    ],
                    [
                        'name' => 'autosync',
                        'label' => __('Mode', 'rrze-answers'),
                        'desc' => __('Synchronize automatically', 'rrze-answers'),
                        'type' => 'checkbox',
                    ],
                    [
                        'name' => 'frequency',
                        'label' => __('Frequency', 'rrze-answers'),
                        'desc' => '',
                        'default' => 'daily',
                        'options' => [
                            'daily' => __('daily', 'rrze-answers'),
                            'twicedaily' => __('twicedaily', 'rrze-answers')
                        ],
                        'type' => 'select'
                    ],
                ],
                'website' => [
                    [
                        'name' => 'redirect_archivpage_uri',
                        'label' => __('Custom archive page', 'rrze-answers'),
                        'desc' => '',
                        'type' => 'select',
                        'options' => Tools::getPageList(),
                        'default' => ''
                    ],
                    [
                        'name' => 'custom_faq_slug',
                        'label' => __('Custom FAQ Slug', 'rrze-answers'),
                        'desc' => '',
                        'type' => 'text',
                        'default' => 'faq',
                        'placeholder' => 'faq'
                    ],
                    [
                        'name' => 'custom_faq_category_slug',
                        'label' => __('Custom FAQ Category Slug', 'rrze-answers'),
                        'desc' => '',
                        'type' => 'text',
                        'default' => 'faq_category',
                        'placeholder' => 'faq_category'

                    ],
                    [
                        'name' => 'custom_faq_tag_slug',
                        'label' => __('Custom FAQ Tag Slug', 'rrze-answers'),
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
            ]
        ]);
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
