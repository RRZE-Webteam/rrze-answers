<?php

namespace RRZE\Answers\Common\Sync;

use RRZE\Answers\Common\API\SyncAPI;

use RRZE\Answers\Common\Tools;

defined('ABSPATH') || exit;

class Sync
{

    protected $type = '';

    protected $frequency = '';

    public function __construct($type, $frequency)
    {
        $this->type = $type;
        $this->frequency = $frequency;

        foreach (['faq', 'glossary', 'placeholder'] as $type) {
            add_action("rrze_answers_auto_sync_{$type}", function () use ($type) {
                $this->runCronjob($type);
            });
        }
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
     
        $options = get_option('rrze-answers');

        $domains = [
            $options['remote_url_faq']
        ];

        $allowSettingsError = ($mode == 'manual' ? true : false);
        $syncRan = false;
        $api = new SyncAPI();

        foreach ($domains as $site_url) {
            $tStartDetail = microtime(true);
            // $categories = (isset($options['remote_categories_faq' . $site_url]) ? implode(',', $options['remote_categories_faq' . $site_url]) : false);

            // 2DO: rrze-answers[remote_categories_faq][host][]


            $categories = (isset($options['remote_categories_faq']) ? implode(',', $options['remote_categories_faq']) : false);
            if ($categories) {
                $identifier = Tools::getIdentifier($site_url);
                $aCnt = $api->setFAQ($identifier, $categories, $site_url);
                $syncRan = true;

                foreach ($aCnt['URLhasSlider'] as $URLhasSlider) {
                    $error_msg = __('Domain', 'rrze-answers') . ' "' . $site_url . '": ' . __('Synchronization error. This FAQ contains sliders ([gallery]) and cannot be synchronized:', 'rrze-answers') . ' ' . $URLhasSlider;
                    Tools::logIt($error_msg . ' | ' . $mode);

                    if ($allowSettingsError) {
                        add_settings_error('Synchronization error', 'syncerror', $error_msg, 'error');
                    }
                }

                $sync_msg = __('Domain', 'rrze-answers') . ' "' . $site_url . '": ' . __('Synchronization completed.', 'rrze-answers') . ' ' . $aCnt['iNew'] . ' ' . __('new', 'rrze-answers') . ', ' . $aCnt['iUpdated'] . ' ' . __('updated', 'rrze-answers') . ' ' . __('and', 'rrze-answers') . ' ' . $aCnt['iDeleted'] . ' ' . __('deleted', 'rrze-answers') . '. ' . __('Required time:', 'rrze-answers') . ' ' . sprintf('%.1f ', microtime(true) - $tStartDetail) . __('seconds', 'rrze-answers');
                Tools::logIt($sync_msg . ' | ' . $mode);

                if ($allowSettingsError) {
                    add_settings_error('Synchronization completed', 'synccompleted', $sync_msg, 'success');
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

        Tools::logIt($sync_msg . ' | ' . $mode);
        return;
    }
}
