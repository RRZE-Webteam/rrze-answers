<?php

namespace RRZE\Answers\Common\Sync;

use RRZE\Answers\Common\API\SyncAPI;

defined('ABSPATH') || exit;

class Sync
{

    protected $type = '';

    protected $frequency = '';

    public function __construct()
    {
        // Actions: sync, add domain, delete domain, delete logfile
        add_action('update_option_rrze-answers', [$this, 'checkSync']);
        add_filter('pre_update_option_rrze-answers', [$this, 'switchTask'], 10, 1);

foreach (['faq', 'glossary', 'synonym'] as $type) {
    add_action("rrze_answers_auto_sync_{$type}", function () use ($type) {
        $this->runCronjob($type);
    });
}
    }

    /**
     * Click on buttons "sync", "add domain", "delete domain" or "delete logfile"
     */
    public function switchTask($options)
    {
        $tab = (!empty($_GET['tab']) ? $_GET['tab'] :'');

        $this->type = substr(strrchr($tab, '-'), 1); // "faq"
        $this->frequency = (!empty($options['remote_frequency_' . $this->type]) ? $options['remote_frequency_' . $this->type] : ''); 

        switch ($tab) {
            case 'import-faq':
            case 'import-synonym':
            case 'import-glossary':
                $mode = (!empty($this->frequency) ? 'automatic' : 'manual');
                $this->doSync($mode);
                $this->setCronjob();
                break;
            case 'del':
                deleteLogfile();
                break;
        }


        return $options;
    }

    public function runCronjob($type)
    {
        $this->type = $type;
        $this->doSync('automatic');
    }

    public function setCronjob()
    {
        date_default_timezone_set('Europe/Berlin');
        $hook = 'rrze_answers_auto_sync_' . $this->type;

        if ($this->frequency == '') {
            wp_clear_scheduled_hook($hook);
            return;
        }

        $nextcron = 0;
        switch ($this->frequency) {
            case 'daily':
                $nextcron = 86400;
                break;
            case 'twicedaily':
                $nextcron = 43200;
                break;
        }

        $nextcron += time();
        wp_clear_scheduled_hook($hook);
        wp_schedule_event($nextcron, $this->frequency, $hook);

        $timestamp = wp_next_scheduled($hook);
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
            if (isset($options['import_faq_donotsync_' . $shortname]) && $options['import_faq_donotsync_' . $shortname] != 'on') {
                $categories = (isset($options['import_faq_categories_' . $shortname]) ? implode(',', $options['import_faq_categories_' . $shortname]) : false);
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
