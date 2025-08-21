<?php

namespace RRZE\Synonym;

defined('ABSPATH') || exit;

use function RRZE\Synonym\Config\logIt;
use function RRZE\Synonym\Config\deleteLogfile;
use RRZE\Synonym\API;
use RRZE\Synonym\CPT;
use RRZE\Synonym\Layout;
use RRZE\Synonym\RESTAPI;
use RRZE\Synonym\Settings;
use RRZE\Synonym\Shortcode;


/**
 * Hauptklasse (Main)
 */
class Main
{
    /**
     * Der vollständige Pfad- und Dateiname der Plugin-Datei.
     * @var string
     */
    protected $pluginFile;

    protected $settings;

    /**
     * Variablen Werte zuweisen.
     * @param string $pluginFile Pfad- und Dateiname der Plugin-Datei
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Es wird ausgeführt, sobald die Klasse instanziiert wird.
     */
    public function onLoaded()
    {
        // Register the Custom RRZE Category, if it is not set by another plugin
        add_filter('block_categories_all', [$this, 'rrze_custom_block_category'], 10, 2);

        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        // Actions: sync, add domain, delete domain, delete logfile
        add_action('update_option_rrze-synonym', [$this, 'checkSync']);
        add_filter('pre_update_option_rrze-synonym', [$this, 'switchTask'], 10, 1);

        $cpt = new CPT();

        $this->settings = new Settings($this->pluginFile);
        $this->settings->onLoaded();

        $restAPI = new RESTAPI();
        $layout = new Layout();
        $shortcode = new Shortcode();

        // Auto-Sync
        add_action('rrze_synonym_auto_sync', [$this, 'runsynonymCronjob']);
    }


    /**
     * Enqueue der globale Skripte.
     */
    public function enqueueScripts()
    {
        wp_register_style('rrze-synonym-style', plugins_url('assets/css/rrze-synonym.css', plugin_basename($this->pluginFile)));
        wp_enqueue_style('rrze-synonym-style');
    }


    /**
     * Click on buttons "sync", "add domain", "delete domain" or "delete logfile"
     */
    public function switchTask($options)
    {
        $api = new API();
        $domains = $api->getDomains();

        // get stored options because they are generated and not defined in config.php
        $storedOptions = get_option('rrze-synonym');
        if (is_array($storedOptions)) {
            $options = array_merge($storedOptions, $options);
        }

        $tab = (isset($_GET['synonymdoms']) ? 'synonymdoms' : (isset($_GET['sync']) ? 'sync' : (isset($_GET['del']) ? 'del' : '')));

        switch ($tab) {
            case 'synonymdoms':
                if ($options['synonymdoms_new_name'] && $options['synonymdoms_new_url']) {
                    // add new domain
                    $aRet = $api->setDomain($options['synonymdoms_new_name'], $options['synonymdoms_new_url'], $domains);

                    if ($aRet['status']) {
                        // url is correct, RRZE-Synonym at given url is in use and shortname is new
                        $domains[$aRet['ret']['cleanShortname']] = $aRet['ret']['cleanUrl'];
                    } else {
                        add_settings_error('synonymdoms_new_url', 'synonymdoms_new_error', $aRet['ret'], 'error');
                    }
                } else {
                    // delete domain(s)
                    foreach ($_POST as $key => $url) {
                        if (substr($key, 0, 11) === "del_domain_") {
                            if (($shortname = array_search($url, $domains)) !== false) {
                                unset($domains[$shortname]);
                                $api->deleteSynonyms($shortname);
                            }
                            unset($options['synonymsync_donotsync_' . $shortname]);
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
        // donotsync is stored in synonymsync_donotsync_<SHORTNAME>
        unset($options['synonymdoms_new_name']);
        unset($options['synonymdoms_new_url']);
        unset($options['synonymsync_shortname']);
        unset($options['synonymsync_url']);
        unset($options['synonymsync_donotsync']);
        unset($options['synonymsync_hr']);

        return $options;
    }


    public function checkSync()
    {
        if (isset($_GET['sync'])) {
            $sync = new Sync();
            $sync->doSync('manual');

            $this->setSynonymCronjob();
        }
    }

    public function runSynonymCronjob()
    {
        // sync hourly
        $sync = new Sync();
        $sync->doSync('automatic');
    }

    public function setSynonymCronjob()
    {
        date_default_timezone_set('Europe/Berlin');

        $options = get_option('rrze-synonym');

        if ($options['synonymsync_autosync'] != 'on') {
            wp_clear_scheduled_hook('rrze_synonym_auto_sync');
            return;
        }

        $nextcron = 0;
        switch ($options['synonymsync_frequency']) {
            case 'daily' :
                $nextcron = 86400;
                break;
            case 'twicedaily' :
                $nextcron = 43200;
                break;
        }

        $nextcron += time();
        wp_clear_scheduled_hook('rrze_synonym_auto_sync');
        wp_schedule_event($nextcron, $options['synonymsync_frequency'], 'rrze_synonym_auto_sync');

        $timestamp = wp_next_scheduled('rrze_synonym_auto_sync');
        $message = __('Next automatically synchronization:', 'rrze-synonym') . ' ' . date('d.m.Y H:i:s', $timestamp);
        add_settings_error('AutoSyncComplete', 'autosynccomplete', $message, 'updated');
        settings_errors();
    }

    /**
     * Adds custom block category if not already present.
     *
     * @param array $categories Existing block categories.
     * @param WP_Post $post Current post object.
     * @return array Modified block categories.
     */
    public function rrze_custom_block_category($categories, $post)
    {
        // Check if there is already a RRZE category present
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === 'rrze') {
                return $categories;
            }
        }

        $custom_category = [
            'slug' => 'rrze',
            'title' => __('RRZE Plugins', 'rrze-synonym'),
        ];

        // Add RRZE to the end of the categories array
        $categories[] = $custom_category;

        return $categories;
    }
}
