<?php

namespace RRZE\Synonym;

defined( 'ABSPATH' ) || exit;

/**
 * REST API for "synonym"
 */
class RESTAPI {
    public function __construct() {
        add_action( 'rest_api_init', [$this, 'createPostMeta'] );
        add_action( 'rest_api_init', [$this, 'addFilters'] );
    }

    public function getMyPostMeta( $object, $attr ){
        return get_post_meta( $object['id'], $attr, TRUE );
    }

    // make API deliver source and lang for synonyms
    public function createPostMeta() {
        $fields = array( 
            'source',
            'lang',
            'synonym',
            'titleLang',
            'remoteID',
            'remoteChanged'
            );

        foreach ( $fields as $field ){
            register_rest_field( 'synonym', $field, array(
                'get_callback'    => [$this, 'getMyPostMeta'],
                'schema'          => null,
            ));
        }
    }
   
    public function addFilterParam( $args, $request ) {
        if ( empty( $request['filter'] ) || ! is_array( $request['filter'] ) ) {
                return $args;
        }
        global $wp;
        $filter = $request['filter'];

        $vars = apply_filters( 'query_vars', $wp->public_query_vars );
        foreach ( $vars as $var ) {
                if ( isset( $filter[ $var ] ) ) {
                        $args[ $var ] = $filter[ $var ];
                }
        }
        return $args;
    }
    
    public function addFilters() {
        add_filter( 'rest_synonym_query', [$this, 'addFilterParam'], 10, 2 );
    }

}
