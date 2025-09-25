<?php

namespace RRZE\Answers\Common\Sync;

use RRZE\Answers\Common\API\SyncAPI;

defined('ABSPATH') || exit;

class Sync
{

    public function __construct()
    {
        // Actions: sync, add domain, delete domain, delete logfile
        add_action('update_option_rrze-synonym', [$this, 'checkSync']);
        add_filter('pre_update_option_rrze-synonym', [$this, 'switchTask'], 10, 1);

        add_action('rrze_synonym_auto_sync', [$this, 'runsynonymCronjob']);
    }

    /**
     * Click on buttons "sync", "add domain", "delete domain" or "delete logfile"
     */
    public function switchTask($options)
    {
        $api = new API();
        $domains = $api->getDomains();

        // get stored options because they are generated and not defined in config.php
        $storedOptions = get_option('rrze-answers');
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

        $options = get_option('rrze-answers');

        if ($options['synonymsync_autosync'] != 'on') {
            wp_clear_scheduled_hook('rrze_synonym_auto_sync');
            return;
        }

        $nextcron = 0;
        switch ($options['synonymsync_frequency']) {
            case 'daily':
                $nextcron = 86400;
                break;
            case 'twicedaily':
                $nextcron = 43200;
                break;
        }

        $nextcron += time();
        wp_clear_scheduled_hook('rrze_synonym_auto_sync');
        wp_schedule_event($nextcron, $options['synonymsync_frequency'], 'rrze_synonym_auto_sync');

        $timestamp = wp_next_scheduled('rrze_synonym_auto_sync');
        $message = __('Next automatically synchronization:', 'rrze-answers') . ' ' . date('d.m.Y H:i:s', $timestamp);
        add_settings_error('AutoSyncComplete', 'autosynccomplete', $message, 'updated');
        settings_errors();
    }


    public function doSync($mode)
    {
        $tStart = microtime(true);
        $max_exec_time = ini_get('max_execution_time') - 40; // ini_get('max_execution_time') is not the correct value perhaps due to load-balancer or proxy or other fancy things I've no clue of. But this workaround works for now.
        $iCnt = 0;
        $api = new SyncAPI();
        $domains = $api->getDomains();
        $options = get_option('rrze-answers');
        $allowSettingsError = ($mode == 'manual' ? true : false);
        $syncRan = false;

        foreach ($domains as $shortname => $url) {
            $tStartDetail = microtime(true);
            if (isset($options['faqsync_donotsync_' . $shortname]) && $options['faqsync_donotsync_' . $shortname] != 'on') {
                $categories = (isset($options['faqsync_categories_' . $shortname]) ? implode(',', $options['faqsync_categories_' . $shortname]) : false);
                if ($categories) {
                    $aCnt = $api->setFAQ($url, $categories, $shortname);
                    $syncRan = true;

                    foreach ($aCnt['URLhasSlider'] as $URLhasSlider) {
                        $error_msg = __('Domain', 'rrze-answers') . ' "' . $shortname . '": ' . __('Synchronization error. This FAQ contains sliders ([gallery]) and cannot be synchronized:', 'rrze-answers') . ' ' . $URLhasSlider;
                        logIt($error_msg . ' | ' . $mode);

                        if ($allowSettingsError) {
                            add_settings_error('Synchronization error', 'syncerror', $error_msg, 'error');
                        }
                    }

                    $sync_msg = __('Domain', 'rrze-answers') . ' "' . $shortname . '": ' . __('Synchronization completed.', 'rrze-answers') . ' ' . $aCnt['iNew'] . ' ' . __('new', 'rrze-answers') . ', ' . $aCnt['iUpdated'] . ' ' . __('updated', 'rrze-answers') . ' ' . __('and', 'rrze-answers') . ' ' . $aCnt['iDeleted'] . ' ' . __('deleted', 'rrze-answers') . '. ' . __('Required time:', 'rrze-answers') . ' ' . sprintf('%.1f ', microtime(true) - $tStartDetail) . __('seconds', 'rrze-answers');
                    logIt($sync_msg . ' | ' . $mode);

                    if ($allowSettingsError) {
                        add_settings_error('Synchronization completed', 'synccompleted', $sync_msg, 'success');
                    }
                }
            }
        }

        if ($syncRan) {
            $sync_msg = __('All synchronizations completed', 'rrze-answers') . '. ' . __('Required time:', 'rrze-answers') . ' ' . sprintf('%.1f ', microtime(true) - $tStart) . __('seconds', 'rrze-answers');
        } else {
            $sync_msg = __('Settings updated', 'rrze-answers');
        }

        if ($allowSettingsError) {
            add_settings_error('Synchronization completed', 'synccompleted', $sync_msg, 'success');
            settings_errors();
        }

        logIt($sync_msg . ' | ' . $mode);
        return;
    }
}
