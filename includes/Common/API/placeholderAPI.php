<?php

namespace RRZE\Placeholder;

defined('ABSPATH') || exit;

define ('SYNONYM_ENDPOINT', 'wp-json/wp/v2/placeholder' );

class API {

    private $aAllCats = array();

    public function setDomain( $site_url, $url, $domains ){
        // returns array('status' => TRUE, 'ret' => array(cleansite_url, cleanUrl)
        // on error returns array('status' => FALSE, 'ret' => error-message)
        $aRet = array( 'status' => FALSE, 'ret' => '' );
        $cleanUrl = trailingslashit( preg_replace( "/^((http|https):\/\/)?/i", "https://", $url ) );
        $cleansite_url = strtolower( preg_replace('/[^A-Za-z0-9]/', '', $site_url ) );

        if ( in_array( $cleanUrl, $domains )){
            $aRet['ret'] = $url . ' ' . __( 'is already in use.', 'rrze-answers' );
            return $aRet;
        }elseif ( array_key_exists( $cleansite_url, $domains )){
            $aRet['ret'] = $cleansite_url . ' ' . __( 'is already in use.', 'rrze-answers' );
            return $aRet;
        }else{
            $request = wp_remote_get( $cleanUrl . SYNONYM_ENDPOINT . '?per_page=1' );
            $status_code = wp_remote_retrieve_response_code( $request );

            if ( $status_code != '200' ){
                $aRet['ret'] = $cleanUrl . ' ' . __( 'is not valid.', 'rrze-answers' );
                return $aRet;
            }else{
                $content = json_decode( wp_remote_retrieve_body( $request ), TRUE );

                if ($content){
                    $cleanUrl = substr( $content[0]['link'], 0 , strpos( $content[0]['link'], '/placeholder' ) ) . '/';
                }else{
                    $aRet['ret'] = $cleanUrl . ' ' . __( 'is not valid.', 'rrze-answers' );
                    return $aRet;    
                }
            } 
        }

        $aRet['status'] = TRUE;
        $aRet['ret'] = array( 'cleansite_url' => $cleansite_url, 'cleanUrl' => $cleanUrl );
        return $aRet;
    }

    protected function isRegisteredDomain( &$url ){
        return in_array( $url, $this->getDomains() );
    }

    public function getDomains(){
        $domains = array();
        $options = get_option( 'rrze-answers' );
        if ( isset( $options['registeredDomains'] ) ){
            foreach( $options['registeredDomains'] as $site_url => $url ){
                $domains[$site_url] = $url;
            }	
        }
        asort( $domains );
        return $domains;
    }
    




    public function deletePlaceholders( $source ){
        // deletes all placeholders by source
        $iDel = 0;
        $allPlaceholders = get_posts( array( 'post_type' => 'placeholder', 'meta_key' => 'source', 'meta_value' => $source, 'numberposts' => -1 ) );

        foreach ( $allPlaceholders as $placeholder ) {
            wp_delete_post( $placeholder->ID, TRUE );
            $iDel++;
        } 
        return $iDel;
    }


    protected function absoluteUrl( $txt, $baseUrl ){
        // converts relative URLs to absolute ones
        $needles = array('href="', 'src="', 'background="');
        $newTxt = '';
        if (substr( $baseUrl, -1 ) != '/' ){
            $baseUrl .= '/';
        } 
        $newBaseUrl = $baseUrl;
        $baseUrlParts = parse_url( $baseUrl );
        foreach ( $needles as $needle ){
            while( $pos = strpos( $txt, $needle ) ){
                $pos += strlen( $needle );
                if ( substr( $txt, $pos, 7 ) != 'http://' && substr( $txt, $pos, 8) != 'https://' && substr( $txt, $pos, 6) != 'ftp://' && substr( $txt, $pos, 7 ) != 'mailto:' ){
                    if ( substr( $txt, $pos, 1 ) == '/' ){
                        $newBaseUrl = $baseUrlParts['scheme'] . '://' . $baseUrlParts['host'];
                    }
                    $newTxt .= substr( $txt, 0, $pos ).$newBaseUrl;
                } else {
                    $newTxt .= substr( $txt, 0, $pos );
                }
                $txt = substr( $txt, $pos );
            }
            $txt = $newTxt . $txt;
            $newTxt = '';
        }
        // convert all elements of srcset, too
        $needle = 'srcset="';
        while( $pos = strpos( $txt, $needle, $pos ) ){
            $pos += strlen( $needle );
            $len = strpos( $txt, '"', $pos ) - $pos;
            $srcset = substr( $txt, $pos, $len );
            $aSrcset = explode( ',', $srcset );
            $aNewSrcset = array();
            foreach( $aSrcset as $src ){
                $src = trim( $src );
                if ( substr( $src, 0, 1 ) == '/' ){
                    $aNewSrcset[] = $newBaseUrl . $src;
                }                                
            }
            $newSrcset = implode( ', ', $aNewSrcset );
            $txt = str_replace( $srcset, $newSrcset, $txt );
        }
        return $txt;
      }

    protected function getPlaceholders( &$url ){
            $placeholders = array();
        $aCategoryRelation = array();
        $page = 1;

        do {
            $request = wp_remote_get( $url . SYNONYM_ENDPOINT . '?page=' . $page );
            $status_code = wp_remote_retrieve_response_code( $request );
            if ( $status_code == 200 ){
                $entries = json_decode( wp_remote_retrieve_body( $request ), true );
                if ( !empty( $entries ) ){
                    if ( !isset( $entries[0] ) ){
                        $entries = array( $entries );
                    }
                    foreach( $entries as $entry ){
                        if ( $entry['source'] == 'website' ){
                            $placeholders[$entry['id']] = array(
                                'id' => $entry['id'],
                                'title' => $entry['title']['rendered'],
                                'placeholder' => $entry['placeholder'],
                                'titleLang' => $entry['titleLang'],
                                'lang' => $entry['lang'],
                                'remoteID' => $entry['remoteID'],
                                'remoteChanged' => $entry['remoteChanged']
                            );
                        }
                    }
                }
            }
            $page++;   
        } while ( ( $status_code == 200 ) && ( !empty( $entries ) ) );

        return $placeholders;
    }

    public function getPlaceholdersRemoteIDs( $source ){
        $aRet = array();
        // $allPlaceholders = get_posts( array( 'post_type' => 'placeholder', 'meta_key' => 'source', 'meta_value' => $source, 'fields' => 'ids', 'numberposts' => -1 ) );
        $allPlaceholders = get_posts(
            ['post_type' => 'placeholder',
            'meta_key' => 'source',
            'meta_value' => $source,
            'nopaging' => true, 
            'fields' => 'ids'
            ]
        );
    
        foreach ( $allPlaceholders as $postID ){
            $remoteID = get_post_meta( $postID, 'remoteID', TRUE );
            $remoteChanged = get_post_meta( $postID, 'remoteChanged', TRUE );
            $aRet[$remoteID] = array(
                'postID' => $postID,
                'remoteChanged' => $remoteChanged
                );
        }
        return $aRet;
    }

    public function setPlaceholders( $url, $site_url ){
        $iNew = 0;
        $iUpdated = 0;
        $iDeleted = 0;

        // get all remoteIDs of stored placeholders to this source ( key = remoteID, value = postID )
        $aRemoteIDs = $this->getPlaceholdersRemoteIDs( $site_url );

        // get all placeholders
        $aPlaceholder = $this->getPlaceholders( $url );

        // set placeholders
        foreach ( $aPlaceholder as $placeholder ){
            if ( isset( $aRemoteIDs[$placeholder['remoteID']] ) ) {
                if ( $aRemoteIDs[$placeholder['remoteID']]['remoteChanged'] < $placeholder['remoteChanged'] ){
                    // update placeholders
                    $post_id = wp_update_post( array(
                        'ID' => $aRemoteIDs[$placeholder['remoteID']]['postID'],
                        'post_name' => sanitize_title( $placeholder['title'] ),
                        'post_title' => $placeholder['title'],
                        'meta_input' => array(
                            'source' => $site_url,
                            'lang' => $placeholder['lang'],
                            'placeholder' => $placeholder['placeholder'],
                            'titleLang' => $placeholder['titleLang'],
                            'remoteID' => $placeholder['remoteID']
                            ),
                        ) ); 
                    $iUpdated++;
                }
                unset( $aRemoteIDs[$placeholder['remoteID']] );
            } else {
                // insert placeholders
                $post_id = wp_insert_post( array(
                    'post_type' => 'placeholder',
                    'post_name' => sanitize_title( $placeholder['title'] ),
                    'post_title' => $placeholder['title'],
                    'comment_status' => 'closed',
                    'ping_status' => 'closed',
                    'post_status' => 'publish',
                    'meta_input' => array(
                        'source' => $site_url,
                        'lang' => $placeholder['lang'],
                        'placeholder' => $placeholder['placeholder'],
                        'titleLang' => $placeholder['titleLang'],
                        'remoteID' => $placeholder['id'],
                        'remoteChanged' => $placeholder['remoteChanged']
                        ),
                    ) );
                $iNew++;
            }
        }

        // delete all other placeholders to this source
        foreach( $aRemoteIDs as $remoteID => $aDetails ){
            wp_delete_post( $aDetails['postID'], TRUE );
            $iDeleted++;
        }

        return array( 
            'iNew' => $iNew,
            'iUpdated' => $iUpdated,
            'iDeleted' => $iDeleted,
        );
    }
}    


