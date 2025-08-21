<?php

namespace RRZE\Synonym;

defined( 'ABSPATH' ) || exit;



/**
 * Custom Post Type "synonym"
 */
class CPT {
    
    private $lang = '';

    public function __construct() {
        $this->lang = substr( get_locale(), 0, 2 );
        add_action( 'init', [$this, 'registerCPT'], 0 );
    }

    
    public function registerCPT() {	    
        $labels = array(
                'name'                => _x( 'synonym', 'synonyms', 'rrze-synonym' ),
                'singular_name'       => _x( 'synonym', 'Single synonym', 'rrze-synonym' ),
                'menu_name'           => __( 'Synonyms', 'rrze-synonym' ),
                'add_new'             => __( 'Add synonym', 'rrze-synonym' ),
                'add_new_item'        => __( 'Add new synonym', 'rrze-synonym' ),
                'edit_item'           => __( 'Edit synonym', 'rrze-synonym' ),
                'all_items'           => __( 'All synonyms', 'rrze-synonym' ),
                'search_items'        => __( 'Search synonym', 'rrze-synonym' ),
        );
        $rewrite = array(
                'slug'                => 'synonym',
                'with_front'          => true,
                'pages'               => true,
                'feeds'               => true,
        );
        $args = array(
                'label'               => __( 'synonym', 'rrze-synonym' ),
                'description'         => __( 'Synonym informations', 'rrze-synonym' ),
                'labels'              => $labels,
                'supports'            => array( 'title' ),
                'hierarchical'        => false,
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_nav_menus'   => false,
                'show_in_admin_bar'   => true,
                'menu_icon'		  => 'dashicons-translation',
                'can_export'          => true,
                'has_archive'         => true,
                'exclude_from_search' => true,
                'publicly_queryable'  => true,
                'query_var'           => 'synonym',
                'rewrite'             => $rewrite,
                'show_in_rest'        => true,
                'rest_base'           => 'synonym',
                'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        register_post_type( 'synonym', $args );
    }
}