<?php

namespace RRZE\Answers;

defined('ABSPATH') || exit;

use RRZE\Answers\Config;
use RRZE\Answers\API\RESTAPI;
use RRZE\Answers\API\SyncAPI;
use RRZE\Answers\CPT\FAQ as CPTFAQ;
use RRZE\Answers\AdminInterfaces\FAQ as AdminFAQ;
use RRZE\Answers\Settings\FAQ as SettingsFAQ;
use RRZE\Answers\Shortcode\FAQ as ShortcodeFAQ;
use RRZE\Answers\Widgets\FAQ as WidgetFAQ;


/**
 * Main class (Main)
 */
class Main
{
    /**
     * The complete path and file name of the plug-in file.
     * @var string
     */

    protected $pluginFile;

    protected $settings;

    /**
     * Assign values to variables.
     * @param string $pluginFile Path and file name of the plugin file
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * It is executed as soon as the class is instantiated.
     */
    public function onLoaded()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('enqueue_block_assets', [$this, 'enqueueScripts']);

        // Actions: sync, add domain, delete domain, delete logfile
        add_action('update_option_rrze-answers', [$this, 'checkSync']);
        add_filter('pre_update_option_rrze-answers', [$this, 'switchTask'], 10, 1);

        // Register the Custom RRZE Category, if it is not set by another plugin
        add_filter('block_categories_all', [$this, 'my_custom_block_category'], 10, 2);

        $config = new Config();
        $cpt = new CPT();
        $restAPI = new RESTAPI();
        $layout = new Layout();
        $shortcode = new Shortcode();

        $this->settings = new Settings($this->pluginFile);
        $this->settings->onLoaded();

        // Widget
        add_action('widgets_init', [$this, 'loadWidget']);

        // Auto-Sync
        add_action('rrze_faq_auto_sync', [$this, 'runFAQCronjob']);
    }


    public function loadWidget()
    {
        $myWidget = new FAQWidget();
        register_widget($myWidget);
    }

    /**
     * Enqueue the global scripts.
     */
    public function enqueueScripts()
    {
        wp_enqueue_style(
            'rrze-answers-css',
            plugins_url('build/css/rrze-answers.css', $this->pluginFile),
            [],
            filemtime(plugin_dir_path($this->pluginFile) . 'build/css/rrze-answers.css')
        );        
    }


    /**
     * Click on buttons "sync", "add domain", "delete domain" or "delete logfile"
     */
    public function switchTask($options)
    {
        $api = new SyncAPI();
        $domains = $api->getDomains();

        // get stored options because they are generated and not defined in config.php
        $storedOptions = get_option('rrze-answers');

        if (is_array($storedOptions) && is_array($options)) {
            $options = array_merge($storedOptions, $options);
        }

        $tab = (isset($_GET['doms']) ? 'doms' : (isset($_GET['sync']) ? 'sync' : (isset($_GET['del']) ? 'del' : '')));

        switch ($tab) {
            case 'doms':
                if ($options['doms_new_name'] && $options['doms_new_url']) {
                    // add new domain
                    $aRet = $api->setDomain($options['doms_new_name'], $options['doms_new_url'], $domains);

                    if ($aRet['status']) {
                        // url is correct, rrze-answers at given url is in use and shortname is new
                        $domains[$aRet['ret']['cleanShortname']] = $aRet['ret']['cleanUrl'];
                    } else {
                        add_settings_error('doms_new_url', 'doms_new_error', $aRet['ret'], 'error');
                    }
                } else {
                    // delete domain(s)
                    foreach ($_POST as $key => $url) {
                        if (substr($key, 0, 11) === "del_domain_") {
                            if (($shortname = array_search($url, $domains)) !== false) {
                                unset($domains[$shortname]);
                                $api->deleteFAQ($shortname);
                                $api->deleteCategories($shortname);
                                $api->deleteTags($shortname);
                            }
                            unset($options['faqsync_categories_' . $shortname]);
                            unset($options['faqsync_donotsync_' . $shortname]);
                        }
                    }
                }
                break;
            case 'sync':
                $options['timestamp'] = time();
                break;
            case 'del':
                deleteLogfile();
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
        unset($options['doms_new_name']);
        unset($options['doms_new_url']);
        unset($options['faqsync_shortname']);
        unset($options['faqsync_url']);
        unset($options['faqsync_categories']);
        unset($options['faqsync_donotsync']);
        unset($options['faqsync_hr']);

        return $options;
    }


    public function checkSync()
    {
        if (isset($_GET['sync'])) {
            $sync = new Sync();
            $sync->doSync('manual');

            $this->setFAQCronjob();
        }
    }

    public function runFAQCronjob()
    {
        // sync hourly
        $sync = new Sync();
        $sync->doSync('automatic');
    }

    public function setFAQCronjob()
    {
        // Remove the use of date_default_timezone_set, as WordPress has its own time zone settings

        $options = get_option('rrze-answers');

        if ($options['faqsync_autosync'] != 'on') {
            wp_clear_scheduled_hook('rrze_faq_auto_sync');
            return;
        }

        $nextcron = 0;
        switch ($options['faqsync_frequency']) {
            case 'daily':
                $nextcron = 86400;
                break;
            case 'twicedaily':
                $nextcron = 43200;
                break;
        }

        $nextcron += time();
        wp_clear_scheduled_hook('rrze_faq_auto_sync');
        wp_schedule_event($nextcron, $options['faqsync_frequency'], 'rrze_faq_auto_sync');

        // Use wp_date() instead of date() to correctly take the time zone into account
        $timestamp = wp_next_scheduled('rrze_faq_auto_sync');
        $message = __('Next automatically synchronization:', 'rrze-answers') . ' ' . wp_date('d.m.Y H:i:s', $timestamp);
        add_settings_error('AutoSyncComplete', 'autosynccomplete', $message, 'updated');
        settings_errors();
    }

    /**
     * Adds custom block category if not already present.
     *
     * @param array   $categories Existing block categories.
     * @param WP_Post $post       Current post object.
     * @return array Modified block categories.
     */
    public function my_custom_block_category($categories, $post)
    {
        // Check if there is already a RRZE category present
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === 'rrze') {
                return $categories;
            }
        }

        $custom_category = [
            'slug'  => 'rrze',
            'title' => __('RRZE', 'rrze-answers'),
        ];

        // Add RRZE to the end of the categories array
        $categories[] = $custom_category;

        return $categories;
    }
}
