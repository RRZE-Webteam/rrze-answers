<?php

namespace RRZE\Answers;

use function RRZE\Answers\plugin;

use RRZE\Answers\Defaults;

use RRZE\Answers\Common\{
    Tools,
    API\RESTAPI,
    API\SyncAPI,
    AdminInterfaces\AdminUI_QA,
    AdminInterfaces\AdminUI_Placeholder,
    // AdminInterfaces\AdminMenu,
    // AdminInterfaces\AdminInterfaces,
    // AdminInterfaces\AdminInterfacesPlaceholder,
    Settings\Settings,
    // Settings\SettingsFAQ,
    CPT\CPTFAQ,
    CPT\CPTGlossary,
    CPT\CPTPlaceholder,
    Sync\Sync,
    Blocks\Blocks,
    Shortcode\ShortcodeFAQ,
    Shortcode\ShortcodeGlossary,
    Shortcode\ShortcodePlaceholder
};

defined('ABSPATH') || exit;

/**
 * Main class
 * 
 * This class serves as the entry point for the plugin.
 * It can be extended to include additional functionality or components as needed.
 * 
 * @package RRZE\Answers\Common
 * @since 1.0.0
 */
class Main
{
    public $defaults;
    public $restapi;
    public $settings;
    // public $settingsFAQ;

    // public $blocks;
    public $shortcodeFAQ;
    private $adminMenu;
    // private $adminInterface;
    private $adminUI;
    private $sync;

    public function __construct()
    {
        $this->cpt();
        add_action('init', [$this, 'onInit']);
        add_filter('wp_kses_allowed_html', [$this, 'my_custom_allowed_html'], 10, 2);
    }

    public function onInit()
    {
        $this->defaults = new Defaults();
        $this->settings();
        $this->restapi = new RESTAPI();

        // $this->adminInterface = new AdminInterfaces('rrze_faq');
        // $this->adminInterface = new AdminInterfaces('rrze_glossary');
        // $this->adminInterface = new AdminInterfacesPlaceholder();
        $this->adminUI = new AdminUI_QA('rrze_faq');
        $this->adminUI = new AdminUI_QA('rrze_glossary');
        $this->adminUI = new AdminUI_Placeholder();

        // $this->adminMenue = new AdminMenu(); // in admin menu there is a maximum of 2 levels. Deactivated this workaround because it wouldn't be best practice.
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueAssets']);
        // add_action('admin_enqueue_scripts', [$this, 'enqueueImportAssets']);
        // add_action('wp_ajax_rrze_answers_get_categories', [$this, 'rrze_answers_get_categories_cb']);

        add_action('pre_update_option_rrze-answers', [$this, 'switchTask'], 10, 1);
        add_action('update_option_rrze-answers', [$this, 'maybeSync'], 10, 2);

        $this->shortcode();
        $this->blocks();
    }


    public function maybeSync($oldOptions, $newOptions)
    {

        if ($oldOptions == $newOptions) {
            return;
        }

        $tab = (!empty($_GET['tab']) ? $_GET['tab'] : '');

        if ($tab == 'import') {
            $sync = new Sync();
            $frequency = (!empty($newOptions['frequency']) ? $newOptions['frequency'] : '');
            $mode = ($frequency ? 'automatic' : 'manual');
            $sync->doSync($mode);
            $sync->setCronjob($frequency);
        }
    }

    public function switchTask($options)
    {
        // get stored options because they are generated and not defined in config.php
        $storedOptions = get_option('rrze-answers');

        if (is_array($storedOptions) && is_array($options)) {
            $options = array_merge($storedOptions, $options);
        }

        $syncAPI = new SyncAPI();
        $domains = $syncAPI->getDomains();

        $tab = (!empty($_GET['tab']) ? $_GET['tab'] : '');

        switch ($tab) {
            case 'domains':
                if ($options['new_url'] && ($options['new_url'] != 'https://')) {
                    // add new domain
                    $identifier = Tools::getIdentifier($options['new_url']);
                    $url = 'https://' . Tools::getHost($options['new_url']);
                    $aRet = $syncAPI->checkDomain($identifier, $url, $domains);

                    if ($aRet['status']) {
                        // url is correct, rrze-answers at given url is in use and identifier is new (generated if not unique)
                        $domains[$identifier] = $url;
                    } else {
                        add_settings_error('new_url', 'domains_new_error', $aRet['msg'], 'error');
                    }
                } else {
                    // delete domain(s)
                    $types = ['faq', 'glossary'];

                    foreach ($_POST as $key => $identifier) {
                        if (substr($key, 0, 11) === "del_domain_") {
                            if ((array_search($identifier, array_keys($domains))) !== false) {
                                unset($domains[$identifier]);
                                foreach ($types as $type) {
                                    $syncAPI->deleteEntries($identifier, $type);
                                    $syncAPI->deleteCategories($identifier, $type);
                                    $syncAPI->deleteTags($identifier, $type);
                                    unset($options[$type . '_categories_' . $identifier]);
                                }
                            }
                        }
                    }
                }
                break;
            case 'import':
                // nothing to do here, see after update options (hook: update_option_rrze-answers)
                break;
            case 'del':
                Tools::deleteLogfile();
                break;
        }


        if (!$domains) {
            // unset this option because $api->getDomains() checks isset(..) because of asort(..)
            unset($options['registeredDomains']);
        } else {
            $options['registeredDomains'] = $domains;
        }

        // we don't need these temporary fields to be stored in database table options
        // domains are stored as shortname and url in registeredDomains
        // categories and donotsync are stored in faqsync_categories_<SHORTNAME> and faqsync_donotsync_<SHORTNAME>
        unset($options['new_name']);
        unset($options['new_url']);
        unset($options['faqsync_shortname']);
        unset($options['faqsync_url']);
        unset($options['faqsync_categories']);
        unset($options['faqsync_donotsync']);
        unset($options['faqsync_hr']);

        return $options;
    }


    // public function rrze_answers_get_categories_cb()
    // {
    //     check_ajax_referer('rrze_answers_sync', '_ajax_nonce');

    //     if (!current_user_can('manage_options')) {
    //         wp_send_json_error(['message' => 'Unauthorized'], 403);
    //     }

    //     $site_url = isset($_POST['site_url']) ? trim(wp_unslash($_POST['site_url'])) : '';
    //     if ($site_url === '') {
    //         wp_send_json_error(['message' => 'Missing parameter: site_url'], 400);
    //     }

    //     // Fetch remote categories
    //     $endpoint = esc_url_raw($site_url) . '/wp-json/wp/v2/rrze_faq_category';
    //     $res = wp_remote_get($endpoint, ['timeout' => 10, 'headers' => ['Accept' => 'application/json']]);

    //     if (is_wp_error($res)) {
    //         wp_send_json_error(['message' => $res->get_error_message()], 500);
    //     }

    //     $code = wp_remote_retrieve_response_code($res);
    //     $body = wp_remote_retrieve_body($res);
    //     if ($code !== 200) {
    //         wp_send_json_error(['message' => "Remote $code", 'body' => $body], $code);
    //     }

    //     $items = json_decode($body, true);
    //     if (!is_array($items)) {
    //         wp_send_json_error(['message' => 'Invalid JSON from remote'], 500);
    //     }

    //     // Load plugin options safely
    //     $options = get_option('rrze-answers');
    //     if (!is_array($options)) {
    //         $options = [];
    //     }

    //     $cats = [];
    //     $selected = [];
    //     $remote_cats_all = isset($options['remote_categories_faq']) && is_array($options['remote_categories_faq'])
    //         ? $options['remote_categories_faq']
    //         : [];

    //     // Selected categories for the current site_url (if previously stored)
    //     $remote_cats_for_site = [];
    //     if (isset($remote_cats_all[$site_url]) && is_array($remote_cats_all[$site_url])) {
    //         $remote_cats_for_site = $remote_cats_all[$site_url];
    //     }

    //     foreach ($items as $item) {
    //         if (!empty($item['slug']) && isset($item['name'])) {
    //             $cats[$item['slug']] = $item['name'];
    //             if (in_array($item['slug'], $remote_cats_for_site, true)) {
    //                 $selected[] = $item['slug'];
    //             }
    //         }
    //     }

    //     // Build remaining site URLs for the secondary dropdown
    //     // Expect all configured site URLs in option 'remote_url_faq' (array of strings)
    //     $all_urls = [];
    //     if (isset($options['remote_url_faq'])) {
    //         if (is_array($options['remote_url_faq'])) {
    //             $all_urls = $options['remote_url_faq'];
    //         } elseif (is_string($options['remote_url_faq']) && $options['remote_url_faq'] !== '') {
    //             // Accept single string for backward compatibility
    //             $all_urls = [$options['remote_url_faq']];
    //         }
    //     }

    //     // Remove current site_url and duplicates
    //     $remaining_urls = array_values(array_unique(array_filter($all_urls, function ($u) use ($site_url) {
    //         return is_string($u) && $u !== '' && $u !== $site_url;
    //     })));

    //     wp_send_json_success([
    //         'categories' => $cats,
    //         'selected' => $selected,
    //         'remaining_urls' => $remaining_urls,
    //         'current_url' => $site_url,
    //     ]);
    // }


    /**
     * Allow needed HTML on post content sanitized by wp_kses_post().
     *
     * @param array  $allowed_tags The current allowed tags/attributes for the given context.
     * @param string $context      KSES context; wp_kses_post() uses 'post'.
     * @return array               Modified allowed tags/attributes.
     */
    function my_custom_allowed_html($allowed_tags, $context)
    {
        // Only alter the 'post' context used by wp_kses_post()
        if ($context !== 'post') {
            return $allowed_tags;
        }

        // 1) Schema.org microdata attributes we want to allow on various elements
        $schema_attrs = [
            'itemscope' => true, // boolean attribute (no value needed)
            'itemtype' => true, // URL to schema type, e.g. https://schema.org/FAQPage
            'itemprop' => true, // property name within the item
            'itemid' => true, // global identifier
            'itemref' => true, // references other elements by ID
        ];

        // 2) HTML5 elements that may carry microdata in your templates/shortcodes
        $tags_to_extend = [
            'div',
            'span',
            'p',
            'a',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'ul',
            'ol',
            'li',
            'section',
            'article',
            'header',
            'footer',
            'main',
            'nav',
            'details',
            'summary'
        ];

        // Ensure details/summary exist with common attributes for accordion UI
        if (!isset($allowed_tags['details'])) {
            $allowed_tags['details'] = [];
        }
        $allowed_tags['details'] = array_merge($allowed_tags['details'], [
            'id' => true,
            'class' => true,
            'open' => true, // render expanded by default
        ]);

        if (!isset($allowed_tags['summary'])) {
            $allowed_tags['summary'] = [];
        }
        $allowed_tags['summary'] = array_merge($allowed_tags['summary'], [
            'id' => true,
            'class' => true,
        ]);

        // 3) Add Schema.org attributes to the listed tags without removing existing ones
        foreach ($tags_to_extend as $tag) {
            if (!isset($allowed_tags[$tag])) {
                $allowed_tags[$tag] = [];
            }
            $allowed_tags[$tag] = array_merge($allowed_tags[$tag], $schema_attrs);
        }

        // 4) (Optional) keep your form elements if you output any in content
        $allowed_tags['select'] = array_merge($allowed_tags['select'] ?? [], [
            'name' => true,
            'id' => true,
            'class' => true,
            'multiple' => true,
            'size' => true,
        ]);

        $allowed_tags['option'] = array_merge($allowed_tags['option'] ?? [], [
            'value' => true,
            'selected' => true,
        ]);

        $allowed_tags['input'] = array_merge($allowed_tags['input'] ?? [], [
            'type' => true,
            'name' => true,
            'id' => true,
            'class' => true,
            'value' => true,
            'placeholder' => true,
            'checked' => true,
            'disabled' => true,
            'readonly' => true,
            'maxlength' => true,
            'size' => true,
            'min' => true,
            'max' => true,
            'step' => true,
        ]);

        return $allowed_tags;
    }

    // public function settingsAll()
    // {
    //     $this->settingsFAQ = new SettingsFAQ(plugin()->getFile());
    // }

    public function cpt()
    {
        $cpt = new CPTFAQ();
        $cpt = new CPTGlossary();
        $cpt = new CPTPlaceholder();
    }


    /**
     * Shortcode method
     * 
     * This method registers a shortcode using the Shortcode class.
     * It can be extended or modified to register additional shortcode as needed.
     * 
     * @return void
     */
    public function shortcode()
    {
        $shortcode = new ShortcodeFAQ();
        $shortcode = new ShortcodeGlossary();
        $shortcode = new ShortcodePlaceholder();
    }

    /**
     * Blocks method
     * 
     * This method registers custom blocks using the Blocks class.
     * It can be extended or modified to register additional blocks as needed.
     * 
     * @return void
     */
    public function blocks()
    {

        $blocks = new Blocks(
            [                                  // Array of block names
                'faq',
            ],
            plugin()->getPath('build/blocks'), // Blocks directory path
            plugin()->getPath()                // Plugin directory path
        );
    }


    /**
     * Settings method
     * 
     * This method sets up the plugin settings using the Settings class.
     * It defines the settings sections and options that will be available in the WordPress admin area
     * and provides validation and sanitization for the settings.
     * 
     * @return void
     */


    public function settings()
    {
        $this->settings = new Settings($this->defaults->get('settings')['page_title']);

        $this->settings->setCapability($this->defaults->get('settings')['capability'])
            ->setOptionName($this->defaults->get('settings')['option_name'])
            ->setMenuTitle($this->defaults->get('settings')['menu_title'])
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        foreach ($this->defaults->get('sections') as $section) {
            $tab = $this->settings->addTab(__($section['title'], 'rrze-answers'), $section['id']);
            $sec = $tab->addSection(__($section['title'], 'rrze-answers'), $section['id']);

            foreach ($this->defaults->get('fields')[$section['id']] as $field) {
                $sec->addOption($field['type'], array_intersect_key(
                    $field,
                    array_flip(['name', 'label', 'description', 'options', 'default', 'sanitize', 'validate', 'placeholder'])
                ));
            }
        }

        $this->settings->build();
    }

    /**
     * Enqueue der globale Skripte.
     */
    public function enqueueAssets()
    {
        wp_register_style(
            'rrze-answers-css',
            plugins_url('build/css/rrze-answers.css', plugin()->getBasename()),
            [],
            filemtime(plugin()->getPath() . 'build/css/rrze-answers.css')
        );

        wp_register_style(
            'rrze-placeholder-css',
            plugins_url('build/css/rrze-placeholder.css', plugin()->getBasename()),
            [],
            filemtime(plugin()->getPath() . 'build/css/rrze-placeholder.css')
        );

        wp_register_script(
            'rrze-answers-accordion',
            plugins_url('build/rrze-answers-accordion.js', plugin()->getBasename()),
            array('jquery'),
            filemtime(plugin()->getPath() . 'build/rrze-answers-accordion.js'),
            true
        );
    }


    // public function enqueueImportAssets(string $hook): void
    // {
    //     wp_register_script(
    //         'rrze-answers-import-ui',
    //         plugins_url('build/rrze-import-ui.js', plugin()->getBasename()),
    //         ['jquery'],
    //         '1.0.0',
    //         true
    //     );

    //     wp_localize_script('rrze-answers-import-ui', 'RRZEAnswersSync', [
    //         'ajaxUrl' => admin_url('admin-ajax.php'),
    //         'nonce' => wp_create_nonce('rrze_answers_sync'),
    //         'optionName' => 'rrze-answers_remote_api_url',
    //         'i18n' => [
    //             'loading' => __('Loading categories…', 'rrze-answers'),
    //             'none' => __('No categories found.', 'rrze-answers'),
    //             'error' => __('Error while loading categories.', 'rrze-answers'),
    //             'selectCategories' => __('Hold Ctrl/Cmd to select multiple categories.', 'rrze-answers'),
    //         ],
    //     ]);

    //     wp_enqueue_script('rrze-answers-import-ui');
    // }

}


