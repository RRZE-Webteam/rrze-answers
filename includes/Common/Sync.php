<?php

namespace RRZE\FAQ;

use RRZE\Answers\API;

defined('ABSPATH') || exit;

class Sync
{

    public function doSync($mode)
    {
        $tStart = microtime(true);
        $max_exec_time = ini_get('max_execution_time') - 40; // ini_get('max_execution_time') is not the correct value perhaps due to load-balancer or proxy or other fancy things I've no clue of. But this workaround works for now.
        $iCnt = 0;
        $api = new API();
        $domains = $api->getDomains();
        $options = get_option('rrze-faq');
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
                        $error_msg = __('Domain', 'rrze-faq') . ' "' . $shortname . '": ' . __('Synchronization error. This FAQ contains sliders ([gallery]) and cannot be synchronized:', 'rrze-faq') . ' ' . $URLhasSlider;
                        logIt($error_msg . ' | ' . $mode);

                        if ($allowSettingsError) {
                            add_settings_error('Synchronization error', 'syncerror', $error_msg, 'error');
                        }
                    }

                    $sync_msg = __('Domain', 'rrze-faq') . ' "' . $shortname . '": ' . __('Synchronization completed.', 'rrze-faq') . ' ' . $aCnt['iNew'] . ' ' . __('new', 'rrze-faq') . ', ' . $aCnt['iUpdated'] . ' ' . __('updated', 'rrze-faq') . ' ' . __('and', 'rrze-faq') . ' ' . $aCnt['iDeleted'] . ' ' . __('deleted', 'rrze-faq') . '. ' . __('Required time:', 'rrze-faq') . ' ' . sprintf('%.1f ', microtime(true) - $tStartDetail) . __('seconds', 'rrze-faq');
                    logIt($sync_msg . ' | ' . $mode);

                    if ($allowSettingsError) {
                        add_settings_error('Synchronization completed', 'synccompleted', $sync_msg, 'success');
                    }
                }
            }
        }

        if ($syncRan) {
            $sync_msg = __('All synchronizations completed', 'rrze-faq') . '. ' . __('Required time:', 'rrze-faq') . ' ' . sprintf('%.1f ', microtime(true) - $tStart) . __('seconds', 'rrze-faq');
        } else {
            $sync_msg = __('Settings updated', 'rrze-faq');
        }

        if ($allowSettingsError) {
            add_settings_error('Synchronization completed', 'synccompleted', $sync_msg, 'success');
            settings_errors();
        }

        logIt($sync_msg . ' | ' . $mode);
        return;
    }
}
