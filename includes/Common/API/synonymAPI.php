<?php

namespace RRZE\Synonym;

defined('ABSPATH') || exit;

define ('SYNONYM_ENDPOINT', 'wp-json/wp/v2/synonym' );

class API {

    private $aAllCats = array();

    public function setDomain( $shortname, $url, $domains ){
        // returns array('status' => TRUE, 'ret' => array(cleanShortname, cleanUrl)
        // on error returns array('status' => FALSE, 'ret' => error-message)
        $aRet = array( 'status' => FALSE, 'ret' => '' );
        $cleanUrl = trailingslashit( preg_replace( "/^((http|https):\/\/)?/i", "https://", $url ) );
        $cleanShortname = strtolower( preg_replace('/[^A-Za-z0-9]/', '', $shortname ) );

        if ( in_array( $cleanUrl, $domains )){
            $aRet['ret'] = $url . ' ' . __( 'is already in use.', 'rrze-answers' );
            return $aRet;
        }elseif ( array_key_exists( $cleanShortname, $domains )){
            $aRet['ret'] = $cleanShortname . ' ' . __( 'is already in use.', 'rrze-answers' );
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
                    $cleanUrl = substr( $content[0]['link'], 0 , strpos( $content[0]['link'], '/synonym' ) ) . '/';
                }else{
                    $aRet['ret'] = $cleanUrl . ' ' . __( 'is not valid.', 'rrze-answers' );
                    return $aRet;    
                }
            } 
        }

        $aRet['status'] = TRUE;
        $aRet['ret'] = array( 'cleanShortname' => $cleanShortname, 'cleanUrl' => $cleanUrl );
        return $aRet;
    }

    protected function isRegisteredDomain( &$url ){
        return in_array( $url, $this->getDomains() );
    }

    public function getDomains(){
        $domains = array();
        $options = get_option( 'rrze_answers' );
        if ( isset( $options['registeredDomains'] ) ){
            foreach( $options['registeredDomains'] as $shortname => $url ){
                $domains[$shortname] = $url;
            }	
        }
        asort( $domains );
        return $domains;
    }
    




    public function deleteSynonyms( $source ){
        // deletes all synonyms by source
        $iDel = 0;
        $allSynonyms = get_posts( array( 'post_type' => 'synonym', 'meta_key' => 'source', 'meta_value' => $source, 'numberposts' => -1 ) );

        foreach ( $allSynonyms as $synonym ) {
            wp_delete_post( $synonym->ID, TRUE );
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

    protected function getSynonyms( &$url ){
            $synonyms = array();
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
                            $synonyms[$entry['id']] = array(
                                'id' => $entry['id'],
                                'title' => $entry['title']['rendered'],
                                'synonym' => $entry['synonym'],
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

        return $synonyms;
    }

    public function getSynonymsRemoteIDs( $source ){
        $aRet = array();
        // $allSynonyms = get_posts( array( 'post_type' => 'synonym', 'meta_key' => 'source', 'meta_value' => $source, 'fields' => 'ids', 'numberposts' => -1 ) );
        $allSynonyms = get_posts(
            ['post_type' => 'synonym',
            'meta_key' => 'source',
            'meta_value' => $source,
            'nopaging' => true, 
            'fields' => 'ids'
            ]
        );
    
        foreach ( $allSynonyms as $postID ){
            $remoteID = get_post_meta( $postID, 'remoteID', TRUE );
            $remoteChanged = get_post_meta( $postID, 'remoteChanged', TRUE );
            $aRet[$remoteID] = array(
                'postID' => $postID,
                'remoteChanged' => $remoteChanged
                );
        }
        return $aRet;
    }

    public function setSynonyms( $url, $shortname ){
        $iNew = 0;
        $iUpdated = 0;
        $iDeleted = 0;

        // get all remoteIDs of stored synonyms to this source ( key = remoteID, value = postID )
        $aRemoteIDs = $this->getSynonymsRemoteIDs( $shortname );

        // get all synonyms
        $aSynonym = $this->getSynonyms( $url );

        // set synonyms
        foreach ( $aSynonym as $synonym ){
            if ( isset( $aRemoteIDs[$synonym['remoteID']] ) ) {
                if ( $aRemoteIDs[$synonym['remoteID']]['remoteChanged'] < $synonym['remoteChanged'] ){
                    // update synonyms
                    $post_id = wp_update_post( array(
                        'ID' => $aRemoteIDs[$synonym['remoteID']]['postID'],
                        'post_name' => sanitize_title( $synonym['title'] ),
                        'post_title' => $synonym['title'],
                        'meta_input' => array(
                            'source' => $shortname,
                            'lang' => $synonym['lang'],
                            'synonym' => $synonym['synonym'],
                            'titleLang' => $synonym['titleLang'],
                            'remoteID' => $synonym['remoteID']
                            ),
                        ) ); 
                    $iUpdated++;
                }
                unset( $aRemoteIDs[$synonym['remoteID']] );
            } else {
                // insert synonyms
                $post_id = wp_insert_post( array(
                    'post_type' => 'synonym',
                    'post_name' => sanitize_title( $synonym['title'] ),
                    'post_title' => $synonym['title'],
                    'comment_status' => 'closed',
                    'ping_status' => 'closed',
                    'post_status' => 'publish',
                    'meta_input' => array(
                        'source' => $shortname,
                        'lang' => $synonym['lang'],
                        'synonym' => $synonym['synonym'],
                        'titleLang' => $synonym['titleLang'],
                        'remoteID' => $synonym['id'],
                        'remoteChanged' => $synonym['remoteChanged']
                        ),
                    ) );
                $iNew++;
            }
        }

        // delete all other synonyms to this source
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


