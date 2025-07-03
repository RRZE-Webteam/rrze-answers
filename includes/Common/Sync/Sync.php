<?php

namespace RRZE\Answers\Common\Sync;

use RRZE\Answers\Common\API\SyncAPI;

defined('ABSPATH') || exit;

class Sync
{

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
