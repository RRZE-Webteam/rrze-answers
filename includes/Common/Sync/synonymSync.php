<?php

namespace RRZE\Synonym;

use RRZE\Synonym\API;
use function RRZE\Synonym\Config\logIt;


defined('ABSPATH') || exit;


class Sync {

    public function doSync( $mode ) {
        $tStart = microtime( TRUE );
        date_default_timezone_set('Europe/Berlin');
        $max_exec_time = ini_get('max_execution_time') - 40; // ini_get('max_execution_time') is not the correct value perhaps due to load-balancer or proxy or other fancy things I've no clue of. But this workaround works for now.
        $iCnt = 0;
        $api = new API();
        $domains = $api->getDomains();
        $options = get_option( 'rrze-synonym' );
        $allowSettingsError = ( $mode == 'manual' ? TRUE : FALSE );
        $syncRan = FALSE;
        foreach( $domains as $shortname => $url ){            
            $tStartDetail = microtime( TRUE );
            if ( isset( $options['synonymsync_donotsync_' . $shortname] ) && $options['synonymsync_donotsync_' . $shortname ] != 'on' ){
                $aCnt = $api->setSynonyms( $url, $shortname  );
                $syncRan = TRUE;
                $sync_msg = __( 'Domain', 'rrze-synonym' ) . ' "' . $shortname . '": ' . __( 'Synchronization completed.', 'rrze-synonym' ) . ' ' . $aCnt['iNew'] . ' ' . __( 'new', 'rrze-synonym' ) . ', ' . $aCnt['iUpdated'] . ' ' . __( ' updated', 'rrze-synonym' ) . ' ' . __( 'and', 'rrze-synonym' ) . ' ' . $aCnt['iDeleted'] . ' ' . __( 'deleted', 'rrze-synonym' ) . '. ' . __('Required time:', 'rrze-synonym') . ' ' . sprintf( '%.1f ', microtime( TRUE ) - $tStartDetail ) . __( 'seconds', 'rrze-synonym' );
                logIt( $sync_msg . ' | ' . $mode );
                if ( $allowSettingsError ){
                    add_settings_error( 'Synchronization completed', 'synccompleted', $sync_msg, 'success' );
                }
            }
        }        

        if ( $syncRan ){
            $sync_msg = __( 'All synchronizations completed', 'rrze-synonym' ) . '. ' . __('Required time:', 'rrze-synonym') . ' ' . sprintf( '%.1f ', microtime( true ) - $tStart ) . __( 'seconds', 'rrze-synonym' );
        } else {
            $sync_msg = __( 'Settings updated', 'rrze-synonym' );
        }
        if ( $allowSettingsError ){
            add_settings_error( 'Synchronization completed', 'synccompleted', $sync_msg, 'success' );
            settings_errors();
        }
        logIt( $sync_msg . ' | ' . $mode );
        return;
    }
}
