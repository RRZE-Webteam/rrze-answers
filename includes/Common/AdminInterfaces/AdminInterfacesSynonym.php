<?php

namespace RRZE\Synonym;

defined( 'ABSPATH' ) || exit;

use RRZE\Synonym\API;
use function RRZE\Synonym\Config\getConstants;


    
/**
 * Layout settings for "synonym"
 */
class Layout {

    protected $constants;


    public function __construct() {
        add_action( 'add_meta_boxes', [$this, 'addSynonymMetaboxes'], 10 );

        add_filter( 'pre_get_posts', [$this, 'orderByTitle'] );

        // show content in box if not editable ( = source is not "website" )
        add_action( 'admin_menu', [$this, 'toggleEditor'] );

        // Table "All synonym"
        add_filter( 'manage_synonym_posts_columns', [$this, 'addColumns'] );        
        add_action( 'manage_synonym_posts_custom_column', [$this, 'getColumnsValues'], 10, 2 );
        add_filter( 'manage_edit-synonym_sortable_columns', [$this, 'addSortableColumns'] );

        // save synonym and titleLang
        add_action( 'save_post_synonym', [$this, 'savePostMeta'] );        

        // add templates for CPT synonym
        add_filter( 'single_template', [$this, 'getSynonymSingleTemplate'] );
        add_filter( 'archive_template', [$this, 'getSynonymArchiveTemplate'] );

        $this->constants = getConstants();
    }

    public static function isFAUTheme() {
        $constants = getConstants();
        $themelist = $constants['fauthemes'];
        $fautheme = false;
        $active_theme = wp_get_theme();
        $active_theme = $active_theme->get( 'Name' );
        if (in_array($active_theme, $themelist)) {
            $fautheme = true;
        }
        return $fautheme;   
    }
    
    public static function getPronunciation($post_id){
        // returns the language in which the long form is pronounced 
        // returns '' if it is different to the website's language
        $constants = getConstants();
        $langlist = $constants['langcodes'];

        $lang = get_post_meta($post_id, 'titleLang', TRUE);
        return ($lang == substr( get_locale(), 0, 2) ? '' : ' (' . __('Pronunciation', 'rrze-synonym') . ': ' . $langlist[$lang] . ')');
    }

    public function getSynonymSingleTemplate( $template ) {
        global $post;
    
        if ( 'synonym' === $post->post_type ) {
            if ( $theme_file = locate_template(array('single-synonym.php'))) {
                $template = $theme_file;
            } else {
                $template = dirname( __FILE__ ) . '/../templates/single-synonym.php';
            }
        }
  
        return $template;
    }

    public function getSynonymArchiveTemplate( $template ) {
        global $post;
    
        if ( 'synonym' === $post->post_type ) {
            if ( $theme_file = locate_template(array('archive-synonym.php'))) {
                $template = $theme_file;
            } else {
                $template = dirname( __FILE__ ) . '/../templates/archive-synonym.php';
            }
        }
  
        return $template;
    }


    public function orderByTitle( $wp_query ) {
        if ( $wp_query->is_main_query() ) {
            $post_type = (isset($wp_query->query['post_type']) ? $wp_query->query['post_type'] : '');
            if ( $post_type == 'synonym') {
                if( ! isset($wp_query->query['orderby'])) {
                    $wp_query->set('orderby', 'title');
                    $wp_query->set('order', 'ASC');
                    $wp_query->set('nopaging', true);
                }
            }
        }
    }

    public function fillContentBox( $post ) {
        $fields = $this->getPostMetas( $post->ID );
        $output = '';

        $output .= '<h1>' . html_entity_decode( $post->post_title ) . '</h1><br>';
        $output .= '<strong>' . __( 'Full form', 'rrze-synonym') . ':</strong>';
        $output .= '<p>' . $fields['synonym'] . '</p>';
        $output .= '<p><i>' . __( 'Pronunciation', 'rrze-synonym' ) . ': ' . $this->constants['langcodes'][$fields['titleLang']] . '</i></p>';

        echo $output;
    }

    public function fillShortcodeBox( $post ) { 
        $ret = '';
        if ( $post->ID > 0 ) {
            $ret .= '<p>[synonym id="' . $post->ID . '"]</p>';
            if ( $post->post_name ){
                $ret .= '<p>[synonym slug="' . $post->post_name . '"]</p>';
            }
            $ret .= '<p>[fau_abbr id="' . $post->ID . '"]</p>';
            if ( $post->post_name ){
                $ret .= '<p>[fau_abbr slug="' . $post->post_name . '"]</p>';
            }
        }
        echo $ret;    
    }

    public function addSynonymMetaboxes(){
        $post_id = ( isset( $_GET['post'] ) ? $_GET['post'] : ( isset ( $_POST['post_ID'] ) ? $_POST['post_ID'] : 0 ) ) ;
        $source = get_post_meta( $post_id, "source", TRUE );
        if ( ( $source == '' ) || ( $source == 'website' ) ){
            add_meta_box(
                'postmetabox',
                __( 'Properties', 'rrze-synonym'),
                [$this, 'postmetaCallback'],
                'synonym',
                'normal',
                'high'
            ); 
        }
        add_meta_box(
            'shortcode_box',
            __( 'Integration in pages and posts', 'rrze-synonym'),
            [$this, 'fillShortcodeBox'],
            'synonym',
            'normal'
        );

    }

    public function toggleEditor(){
        $post_id = ( isset( $_GET['post'] ) ? $_GET['post'] : ( isset ( $_POST['post_ID'] ) ? $_POST['post_ID'] : 0 ) ) ;
        if ( $post_id ){            
            if ( get_post_type( $post_id ) == 'synonym' ) {
                $source = get_post_meta( $post_id, "source", TRUE );
                if ( $source ){
                    if ( $source != 'website' ){
                        $api = new API();
                        $domains = $api->getDomains();
                        $remoteID = get_post_meta( $post_id, "remoteID", TRUE );
                        $link = $domains[$source] . 'wp-admin/post.php?post=' . $remoteID . '&action=edit';
                        remove_post_type_support( 'synonym', 'title' );
                        remove_meta_box( 'submitdiv', 'synonym', 'side' );  
                        // remove_meta_box( 'postmetabox', 'synonym', 'normal' );
                        add_meta_box(
                            'read_only_content_box',
                            __( 'This synonym cannot be edited because it is synchronized', 'rrze-synonym') . '. <a href="' . $link . '" target="_blank">' . __('You can edit it at the source', 'rrze-synonym') . '</a>',
                            [$this, 'fillContentBox'],
                            'synonym',
                            'normal',
                            'high'
                        );
                    }
                }
            }    
        }
    }

    public function addColumns( $columns ) {
        $columns['source'] = __( 'Source', 'rrze-synonym' );
        $columns['id'] = __( 'ID', 'rrze-synonym' );
        return $columns;
    }

    public function addSortableColumns( $columns ) {
        $columns['source'] = __( 'Source', 'rrze-synonym' );
        $columns['id'] = __( 'ID', 'rrze-synonym' );
        return $columns;
    }

    public function getPostMetas( $postID ){
        return array( 
            'source' => get_post_meta( $postID, 'source', TRUE ),
            'synonym' => get_post_meta( $postID, 'synonym', TRUE ),
            'titleLang' => get_post_meta( $postID, 'titleLang', TRUE )
        );
    }

    public function postmetaCallback( $meta_id ) {
        $fields = $this->getPostMetas( $meta_id->ID );

        // synonym
        $output = '<p><label for="synonym">' . __( 'Full form', 'rrze-synonym') . '</label></p>';
        $output .= '<textarea rows="3" cols="60" name="synonym" id="synonym">'. esc_attr( $fields['synonym'] ) .'</textarea>';
        $output .= '<p class="description">' . __( 'Enter here the long, written form of the synonym. This text will later replace the shortcode. Attention: Breaks or HTML statements are not accepted.', 'rrze-synonym' ) . '</p>';// Geben Sie hier die lange, ausgeschriebene Form des Synonyms ein. Mit diesem Text wird dann im späteren Gebrauch der verwendete Shortcode ersetzt. Achtung: Umbrüche oder HTML-Anweisungen werden nicht übernommen.

        // langTitle
        $selectedLang = ( $fields['titleLang'] ? $fields['titleLang'] : substr( get_locale(), 0, 2) );
        $output .= '<br><select class="" id="titleLang" name="titleLang">';
        foreach( $this->constants['langcodes'] as $lang => $desc ){
            $selected = ( $lang == $selectedLang ? ' selected' : '' );
            $output .= '<option value="' . $lang . '"' . $selected . '>' . $desc . '</option>';
        }
        $output .= '</select>';
        $output .= '<p class="description">' . __( 'Choose the language in which the long form is pronounced.', 'rrze-synonym' ) . '</p>'; // Wählen Sie die Sprache, in der die Langform ausgesprochen wird. 

        echo $output;
    }

    public function getColumnsValues( $column_name, $post_id ) {
        if( $column_name == 'id' ) {
            echo $post_id;
        }
        if( $column_name == 'source' ) {
            $source = get_post_meta( $post_id, 'source', true );
            echo $source;
        }
    }

    public function savePostMeta( $postID ){
        if ( ! current_user_can( 'edit_post', $postID ) || ! isset( $_POST['synonym'] ) || ! isset( $_POST['titleLang'] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ){
            return $postID;
        }

        update_post_meta( $postID, 'source', 'website' );        
        update_post_meta( $postID, 'remoteID', $postID );
        update_post_meta( $postID, 'synonym', sanitize_text_field( $_POST['synonym'] ) );        
        update_post_meta( $postID, 'titleLang', sanitize_text_field( $_POST['titleLang'] ) );
        $remoteChanged = get_post_timestamp( $postID, 'modified' );
        update_post_meta( $postID, 'remoteChanged', $remoteChanged );
    }


}
