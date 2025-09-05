<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Tools;
/**
 * Shortcode
 */
class ShortcodeGlossary
{



    private $settings = '';
    private $pluginname = '';

    public function __construct() {
        $this->settings = $this->getShortcodeSettings();
        $this->pluginname = $this->settings['block']['blockname'];
        add_shortcode( 'glossary', [ $this, 'shortcodeOutput' ]);
        add_shortcode( 'fau_glossar', [ $this, 'shortcodeOutput' ]);
        add_action('admin_head', [$this, 'setMCEConfig']);
        add_filter('mce_external_plugins', [$this, 'addMCEButtons']);
    }

function getShortcodeSettings(){
	return [
		'block' => [
            'blocktype' => 'rrze-glossary/glossary',
			'blockname' => 'glossary',
			'title' => 'RRZE Glossary',
			'category' => 'widgets',
            'icon' => 'book',
            'tinymce_icon' => 'info',
		],
        'register' => [
			'values' => [
                [
                    'id' => '',
                    'val' => __( 'none', 'rrze-glossary' )
                ],
                [
                    'id' => 'category',
                    'val' => __( 'Categories', 'rrze-glossary' )
                ],
                [
                    'id' => 'tag',
                    'val' => __( 'Tags', 'rrze-glossary' )
                ]			
            ],
			'default' => '',
			'field_type' => 'select',
			'label' => __( 'Register content', 'rrze-glossary' ),
			'type' => 'string'
		],
        'registerstyle' => [
			'values' => [
                [
                    'id' => '',
                    'val' => __( '-- hidden --', 'rrze-glossary' )
                ],
                [
                    'id' => 'a-z',
                    'val' => __( 'A - Z', 'rrze-glossary' )
                ],
                [
                    'id' => 'tagcloud',
                    'val' => __( 'Tagcloud', 'rrze-glossary' )
                ],			
                [
                    'id' => 'tabs',
                    'val' => __( 'Tabs', 'rrze-glossary' )
                ]
            ],
			'default' => 'a-z',
			'field_type' => 'select',
			'label' => __( 'Register style', 'rrze-glossary' ),
			'type' => 'string'
        ],        
		'category' => [
			'default' => '0',
			'field_type' => 'text',
			'label' => __( 'Categories', 'rrze-glossary' ),
			'type' => 'string'
        ],
		'tag' => [
			'default' => 0,
			'field_type' => 'text',
			'label' => __( 'Tags', 'rrze-glossary' ),
			'type' => 'string'
        ],
		'id' => [
			'default' => NULL,
			'field_type' => 'text',
			'label' => __( 'Glossary', 'rrze-glossary' ),
			'type' => 'number'
		],
		'hide_accordion' => [
			'field_type' => 'toggle',
			'label' => __( 'Hide accordion', 'rrze-glossary' ),
			'type' => 'boolean',
			'default' => FALSE,
			'checked'   => FALSE
		],	  
		'hide_title' => [
			'field_type' => 'toggle',
			'label' => __( 'Hide title', 'rrze-glossary' ),
			'type' => 'boolean',
			'default' => FALSE,
			'checked'   => FALSE
		],	  
		'expand_all_link' => [
			'field_type' => 'toggle',
			'label' => __( 'Show "expand all" button', 'rrze-glossary' ),
			'type' => 'boolean',
			'default' => FALSE,
			'checked'   => FALSE
		],	  
		'load_open' => [
			'field_type' => 'toggle',
			'label' => __( 'Load website with opened accordions', 'rrze-glossary' ),
			'type' => 'boolean',
			'default' => FALSE,
			'checked'   => FALSE
		],	  
		'color' => [
			'values' => [
                [
                    'id' => 'med',
                    'val' => 'med'
                ],
                [
                    'id' => 'nat',
                    'val' => 'nat'
                ],
                [
                    'id' => 'rw',
                    'val' => 'rw'
                ],
                [
                    'id' => 'phil',
                    'val' => 'phil'
                ],
                [
                    'id' => 'tk',
                    'val' => 'tk'
                ],
			],
			'default' => 'tk',
			'field_type' => 'select',
			'label' => __( 'Color', 'rrze-glossary' ),
			'type' => 'string'
		],
		'style' => [
			'values' => [
                [
                    'id' => '',
                    'val' => __('none', 'rrze-faq' )
                ],
                [
                    'id' => 'light',
                    'val' => 'light'
                ],
                [
                    'id' => 'dark',
                    'val' => 'dark'
                ],
			],
			'default' => '',
			'field_type' => 'select',
			'label' => __( 'Style', 'rrze-faq' ),
			'type' => 'string'
		],
		'additional_class' => [
			'default' => '',
			'field_type' => 'text',
			'label' => __( 'Additonal CSS-class(es) for sourrounding DIV', 'rrze-glossary' ),
			'type' => 'string'
		],
        'sort' => [
			'values' => [
                [
                    'id' => 'title',
                    'val' => __( 'Title', 'rrze-glossary' )
                ],
                [
                    'id' => 'id',
                    'val' => __( 'ID', 'rrze-glossary' )
                ],
                [
                    'id' => 'sortfield',
                    'val' => __( 'Sort field', 'rrze-glossary' )
                ],
			],
			'default' => 'title',
			'field_type' => 'select',
			'label' => __( 'Sort', 'rrze-glossary' ),
			'type' => 'string'
		],
        'order' => [
			'values' => [
                [
                    'id' => 'ASC',
                    'val' => __( 'ASC', 'rrze-glossary' )
                ],
                [
                    'id' => 'DESC',
                    'val' => __( 'DESC', 'rrze-glossary' )
                ],
			],
			'default' => 'ASC',
			'field_type' => 'select',
			'label' => __( 'Order', 'rrze-glossary' ),
			'type' => 'string'
		],
		'hstart' => [
			'default' => 2,
			'field_type' => 'text',
			'label' => __( 'Heading level of the first heading', 'rrze-glossary' ),
			'type' => 'number' 
		],
    ];
}
    
    private function getLetter( &$txt ) {
        return mb_strtoupper( mb_substr( remove_accents( $txt ), 0, 1 ), 'UTF-8');
    }

    private function createAZ( &$aSearch ){
        if ( count( $aSearch ) == 1 ){
            return '';
        }
        $ret = '<div class="fau-glossar"><ul class="letters">';
        foreach ( range( 'A', 'Z' ) as $a ) {
            if ( array_key_exists( $a, $aSearch ) ) {
                $ret .= '<li class="filled"><a href="#letter-'.$a.'">'.$a.'</a></li>';
            } else {
                $ret .= '<li aria-hidden="true" role="presentation"><span>'.$a.'</span></li>';
            }
        }
        return $ret . '</ul></div>';
    }

    private function createTabs( &$aTerms, $aPostIDs ) {
        if ( count( $aTerms ) == 1 ){
            return '';
        }
        $ret = '<div class="fau-glossar"><ul class="letters">';
        foreach( $aTerms as $name => $aDetails ){
            $ret .= '<a href="#ID-' . $aDetails['ID'] . '">' . $name . '</a> | ';
        }
        return rtrim( $ret, ' | ' ) . '</div>';
    }    

    private function createTagcloud( &$aTerms, $aPostIDs ) {
        if ( count( $aTerms ) == 1 ){
            return '';
        }
        $ret = '<div class="fau-glossar"><ul class="letters">';
        $smallest = 12;
        $largest = 22;
        $aCounts = array();
        foreach( $aTerms as $name => $aDetails ){
            $aCounts[$aDetails['ID']] = count( $aPostIDs[$aDetails['ID']] );
        }
        $iMax = max( $aCounts );
        $aSizes = array();
        foreach ( $aCounts as $ID => $cnt ){
            $aSizes[$ID] = round( ( $cnt / $iMax ) * $largest, 0 );
            $aSizes[$ID] = ( $aSizes[$ID] < $smallest ? $smallest : $aSizes[$ID] );
        }
        foreach( $aTerms as $name => $aDetails ){
            $ret .= '<a href="#ID-' . $aDetails['ID'] . '" style="font-size:'. $aSizes[$aDetails['ID']] .'px">' . $name . '</a> | ';
        }
        return rtrim( $ret, ' | ' ) . '</div>';
    }

    private function getTaxQuery( &$aTax ){
        $ret = '';
        $aTmp = array();
        foreach( $aTax as $field => $aVal ){
            if ( $aVal[0] ){
                $aTmp[] = array(
                    'taxonomy' => 'glossary_' . $field,
                    'field' => 'slug',
                    'terms' => $aVal
                );    
            }
        }
        if ( $aTmp ){
            $ret = $aTmp;
            if ( count( $aTmp ) > 1 ){
                $ret['relation'] = 'AND';
            }
        }
        return $ret;
    }

    private function searchArrayByKey( &$needle, &$aHaystack ){
        foreach( $aHaystack as $k => $v ){
            if ( $k === $needle ){
                return $v;
            }
        }
        return FALSE;
    }

    private function getSchema( $postID, $question, $answer ){
        $schema = '';
        $source = get_post_meta( $postID, "source", TRUE );
        $answer = wp_strip_all_tags( $answer, TRUE );
        if ( $source == 'website' ){
            $schema = RRZE_SCHEMA_TITLE_START . $question . RRZE_SCHEMA_TITLE_END;
            $schema .= RRZE_SCHEMA_CONTENT_START . $answer . RRZE_SCHEMA_CONTENT_END ;
        }
        return $schema;
    }

/**
     * Translates composite shortcode attributes into individual properties
     * 
     * Splits the values of attributes such as “glossary”, “hide”, “show” and “class” into sub-terms 
     * and assigns these to logical individual fields in the attribute array. This simplifies further internal processing.
     * 
     * @param array $atts Reference to the shortcode attribute array
     * @return void
     */
    private function translateNewAttributes(array &$atts): void
    {
        // translate new attributes
        if (isset($atts['glossary'])) {
            $parts = explode(' ', $atts['glossary']);
            foreach ($parts as $part) {
                $part = trim($part);
                switch ($part) {
                    case 'category':
                    case 'tag':
                        $atts['glossary'] = $part;
                        break;
                    case 'a-z':
                    case 'tabs':
                    case 'tagcloud':
                        $atts['glossarystyle'] = $part;
                        break;
                }
            }
        }

        if (isset($atts['hide'])) {
            $parts = explode(' ', $atts['hide']);
            foreach ($parts as $part) {
                $part = trim($part);
                switch ($part) {
                    case 'title':
                        $atts['hide_title'] = true;
                        break;
                    case 'accordion':
                    case 'accordeon':
                        $atts['hide_accordion'] = true;
                        break;
                    case 'glossary':
                        $atts['glossarystyle'] = '';
                        break;
                }
            }
        }

        if (isset($atts['show'])) {
            $parts = explode(' ', $atts['show']);
            foreach ($parts as $part) {
                $part = trim($part);
                switch ($part) {
                    case 'expand-all-link':
                        $atts['expand_all_link'] = ' expand-all-link="true"';
                        break;
                    case 'load-open':
                        $atts['load_open'] = ' load="open"';
                        break;
                }
            }
        }

        $atts['additional_class'] = isset($atts['additional_class']) ? $atts['additional_class'] : '';
        if (isset($atts['class'])) {
            $parts = explode(' ', $atts['class']);
            foreach ($parts as $part) {
                $part = trim($part);
                switch ($part) {
                    case 'med':
                    case 'nat':
                    case 'phil':
                    case 'rw':
                    case 'tk':
                        $atts['color'] = $part;
                        break;
                    default:
                        $atts['additional_class'] .= ' ' . $part;
                        break;
                }
            }
        }

        $atts['sort'] = (isset($atts['sort']) && ($atts['sort'] == 'title' || $atts['sort'] == 'id' || $atts['sort'] == 'sortfield')) ? $atts['sort'] : 'title';

        $atts['expand_all_link'] = (isset($atts['expand_all_link']) && $atts['expand_all_link'] ? ' expand-all-link="true"' : '');
        $atts['load_open'] = (isset($atts['load_open']) && $atts['load_open'] ? ' load="open"' : '');
    }


        /**
     * Outputs explicitly requested FAQs as accordion or simple content.
     *
     * Supports both Gutenberg blocks (multiple IDs as an array) and the classic editor (comma-separated).
     *
     * @param mixed $id Single ID or array of IDs
     * @param bool $gutenberg Whether Gutenberg is used
     * @param int $hstart HTML heading level
     * @param string $style Inline styles for the accordion
     * @param bool $masonry Whether tiles should be displayed (fake masonry - see https://github.com/RRZE-Webteam/rrze-faq/issues/105#issuecomment-2873361435 )
     * @param string $expand_all_link Attribute for “expand all” link
     * @param bool $hide_accordion Whether the accordion should be suppressed
     * @param bool $hide_title Whether the title should be suppressed
     * @param string $color Color attribute of the accordion
     * @param string $load_open Attribute for open state
     * @return string The generated HTML content
     */
    private function renderExplicitFAQs($id, bool $gutenberg, int $hstart, string $style, bool $masonry, string $expand_all_link, bool $hide_accordion, bool $hide_title, string $color, string $load_open): string
    {
        $content = '';
        $this->bSchema = false;

        // EXPLICIT FAQ(s)
        if ($gutenberg) {
            $aIDs = $id;
        } else {
            // classic editor
            $aIDs = explode(',', $id);
        }

        foreach ($aIDs as $id) {
            $id = trim($id);
            if ($id) {
                $question = get_the_title($id);
                $anchorfield = get_post_meta($id, 'anchorfield', true);

                if (empty($anchorfield)) {
                    $anchorfield = 'ID-' . $id;
                }

                $answer = str_replace(']]>', ']]&gt;', apply_filters('the_content', get_post_field('post_content', $id)));
                $useSchema = (get_post_meta($id, 'source', true) === 'website');

                if ($useSchema){
                    $this->bSchema = true;
                }

                if ($hide_accordion) {
                    $content .= Tools::renderFAQItem($question, $answer, $hstart, $useSchema);
                } else {
                    $content .= Tools::renderFAQItemAccordion($anchorfield, $question, $answer, $color, $load_open, $useSchema);
                }
            }
        }

        return $content;
    }


    /**
     * Generieren Sie die Shortcode-Ausgabe
     * @param  array   $atts Shortcode-Attribute
     * @param  string  $content Beiliegender Inhalt
     * @return string Gib den Inhalt zurück
     */
    public function shortcodeOutput( $atts ) {


        if (empty($atts)) {
            $atts = array();
        } else {
            $atts = array_map('sanitize_text_field', $atts);
        }

        // to make it compatible to rrze-answers (using one translateNewAttributes())
        if ( isset( $atts['register'] ) ){
            $atts['glossary'] = $atts['register'];
            unset($atts['register']);
        }

        $this->translateNewAttributes($atts);

        // merge given attributes with default ones
        $atts_default = array();
        foreach( $this->settings as $k => $v ){
            if ( $k != 'block' ){
                $atts_default[$k] = $v['default'];
            }
        }
        $atts = shortcode_atts( $atts_default, $atts );
        extract( $atts );
        
        $content = '';
        $glossarystyle = (isset($glossarystyle) ? $glossarystyle : '');
        $hide_title = (isset($hide_title) ? $hide_title : false);
        $color = (isset($color) ? $color : '');
        $style = (isset($style) ? 'style="' . $style . '"' : '');

        $gutenberg = (is_array($id) ? true : false);

        if ($id && (!$gutenberg || $gutenberg && $id[0])) {
            $content = $this->renderExplicitFAQs($id, $gutenberg, $hstart, $style, $masonry, $expand_all_link, $hide_accordion, $hide_title, $color, $load_open);
        } else {
            $content = $this->renderFilteredFAQs($atts, $hstart, $style, $expand_all_link, $hide_accordion, $hide_title, $color, $load_open, $sort, $order, $category, $tag, $glossary, $glossarystyle);
        }




        if ( $id && ( !$gutenberg || $gutenberg && $id[0] ) ) {
            // EXPLICIT glossary / glossaries
            if ( $gutenberg ){
                $aIDs = $id;
            } else {
                // classic editor
                $aIDs = explode( ',', $id );
            }
            $found = FALSE;
            $accordion = '[collapsibles hstart="' . $hstart . '" ' . $style . ' ' . $expand_all_link . ']';

            foreach ( $aIDs as $registerID ){
                $registerID = trim( $registerID );
                if ( $registerID ){
                    $title = get_the_title( $registerID );
                    $description = str_replace( ']]>', ']]&gt;', apply_filters( 'the_content',  get_post_field( 'post_content', $registerID ) ) );
                    if ( !isset( $description ) || ( mb_strlen( $description ) < 1)) {
                        $description = get_post_meta( $id, 'description', true );
                    }
                    if ( $hide_accordion ){
                        $content .= ( $hide_title ? '' : '<h' . $atts['hstart'] . '>' . $title . '</h' . $atts['hstart'] . '>' ) . ( $description ? '<p>' . $description . '</p>' : '' );
                    } else {
                        if ( $description) {
                            $accordion .= '[collapse title="' . $title . '" color="' . $color . '" name="ID-' . $registerID . '"' . $load_open . ']' . $description . '[/collapse]';
                            $schema .= $this->getSchema( $registerID, $title, $description );
                        }    
                    }        
                    $found = TRUE;
                }
            }
            if ( $found && !$hide_accordion ){
                $accordion .= '[/collapsibles]';
                $content = do_shortcode( $accordion );    
            }
        } else {
            // attribute category or tag is given or none of them
            $aLetters = array();
            $aCategory = array();
            $aTax = array();
            $tax_query = '';

            // $postQuery = array('post_type' => 'glossary', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => $sort, 'order' => $order, 'suppress_filters' => false);
            $postQuery = array('post_type' => 'glossary', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => false);
            if ( $sort == 'sortfield' ){
                $postQuery['orderby'] = array( 
                    'meta_value' => $order,
                    'title' => $order
                );
                $postQuery['meta_key'] = 'sortfield';
            } else {
                $postQuery['orderby'] = $sort;
                $postQuery['order'] = $order;
            }

            $fields = array( 'category', 'tag' );
            foreach( $fields as $field ){
                if ( !is_array( $$field ) ){
                    $aTax[$field] = explode(',', trim( $$field ) );
                }elseif ( $$field[0] ) {
                    $aTax[$field] = $$field;
                }
            }
            if ( $aTax ){
                $tax_query = $this->getTaxQuery( $aTax );
                if ( $tax_query ){
                    $postQuery['tax_query'] = $tax_query;
                }    
            }
            $posts = get_posts( $postQuery );

            if ( $posts ){
                if ( $register ){
                    // attribut register is given
                    // get all used tags or categories
                    $aUsedTerms = array();
                    $aPostIDs = array();
                    foreach( $posts as $post ) {
                        // get all tags for each post
                        $aTermIds = array();
                        $valid_term_ids = array();
                        if ( $register == 'category' && $category ){
                            if ( !is_array( $category ) ){
                                $aCats = array_map( 'trim', explode( ',', $category ) );                
                            }else{
                                $aCats = $category;
                            }
                            foreach ( $aCats as $slug ){
                                $filter_term = get_term_by( 'slug', $slug, 'glossary_category' );
                                if ( $filter_term ){
                                    $valid_term_ids[] = $filter_term->term_id;
                                } 
                            }
                        } elseif ( $register == 'tag' && $tag ){
                            if ( !is_array( $tag ) ){
                                $aTags = array_map( 'trim', explode( ',', $tag ) );                
                            }else{
                                $aTags = $tag;
                            }
                            foreach ( $aTags as $slug ){
                                $filter_term = get_term_by( 'slug', $slug, 'glossary_tag' );
                                if ( $filter_term ){
                                    $valid_term_ids[] = $filter_term->term_id;
                                } 
                            }
                        }     
                        $terms = wp_get_post_terms( $post->ID, 'glossary_' . $register );

                        if ( $terms && !isset($terms->errors)){
                            foreach( $terms as $t ){
                                if ( $valid_term_ids && in_array( $t->term_id, $valid_term_ids ) === FALSE ){
                                    continue;
                                }
                                $aTermIds[] = $t->term_id;
                                $letter = $this->getLetter( $t->name );
                                $aLetters[$letter] = TRUE; 
                                $aUsedTerms[$t->name] = array( 'letter' => $letter, 'ID' => $t->term_id );
                                $aPostIDs[$t->term_id][] = $post->ID;
                            }
                        }                    
                    }
                    ksort( $aUsedTerms );
                    $anchor = 'ID';
                    if ( $aLetters ){
                        switch( $registerstyle ){
                            case 'a-z': 
                                $content = $this->createAZ( $aLetters );
                                $anchor = 'letter';
                                break;
                            case 'tabs': 
                                $content = $this->createTabs( $aUsedTerms, $aPostIDs );
                                break;
                            case 'tagcloud': 
                                $content = $this->createTagcloud( $aUsedTerms, $aPostIDs );
                                break;            
                        }
                    }
                    $accordion = '[collapsibles hstart="' . $hstart . '" ' . $style . ' ' . $expand_all_link . ']';
                    $last_anchor = '';
                    foreach ( $aUsedTerms as $k => $aVal ){
                        if ( $registerstyle == 'a-z' && $content ){
                            $accordion_anchor = '';
                            $accordion .= ( $last_anchor != $aVal[$anchor] ? '<h2 id="' . $anchor . '-' . $aVal[$anchor] . '">' . $aVal[$anchor] . '</h2>' : '' );
                        } else {
                            $accordion_anchor = 'name="' . $anchor . '-' . $aVal[$anchor] . '"';
                        }

                        $accordion .= '[collapse title="' . $k . '" color="' . $color . '" ' . $accordion_anchor . $load_open . ']';

                        // find the postIDs to this tag
                        $aIDs = $this->searchArrayByKey( $aVal['ID'], $aPostIDs );

                        foreach ( $aIDs as $ID ){
                            $tmp = str_replace( ']]>', ']]&gt;', apply_filters( 'the_content',  get_post_field('post_content', $ID) ) );
                            if ( !isset( $tmp ) || (mb_strlen( $tmp ) < 1)) {
                                $tmp = get_post_meta( $ID, 'description', true );
                            }
                            $title = get_the_title( $ID );
                            $accordion .= '[accordion][accordion-item title="' . $title . '" name="innerID-' . $ID . '"]' . $tmp . '[/accordion-item][/accordion]';
                            $schema .= $this->getSchema( $ID, $title, $tmp );
                        }
                        $accordion .= '[/collapse]';
                        $last_anchor = $aVal[$anchor];
                    }
                    $accordion .= '[/collapsibles]';

                    $content .= do_shortcode( $accordion );
                } else {  
                    // attribut register is not given  
                    if ( !$hide_accordion ){
                        $accordion = '[collapsibles hstart="' . $hstart . '" ' . $style . ' ' . $expand_all_link . ']';
                    }           
                    $last_anchor = '';
                    foreach( $posts as $post ) {
                        $title = get_the_title( $post->ID );
                        $letter = $this->getLetter( $title );
                        $aLetters[$letter] = TRUE;     
                        
                        $tmp = str_replace( ']]>', ']]&gt;', apply_filters( 'the_content',  get_post_field( 'post_content', $post->ID ) ) );
                        if ( !isset( $tmp ) || ( mb_strlen( $tmp ) < 1 ) ) {
                            $tmp = get_post_meta( $post->ID, 'description', true );
                        }

                        if ( !$hide_accordion ){
                            $accordion_anchor = '';
                            $accordion_anchor = 'name="ID-' . $post->ID . '"';
                            if ( $registerstyle == 'a-z' && count( $posts) > 1 ){
                                $accordion .= ( $last_anchor != $letter ? '<h' . $atts['hstart'] . ' id="letter-' . $letter . '">' . $letter . '</h' . $atts['hstart'] . '>' : '' );
                            }
                            $accordion .= '[collapse title="' . $title . '" color="' . $color . '" ' . $accordion_anchor . $load_open . ']' . $tmp . '[/collapse]';               
                        } else {
                            $content .= ( $hide_title ? '' : '<h' . $atts['hstart'] . '>' . $title . '</h' . $atts['hstart'] . '>' ) . ( $tmp ? '<p>' . $tmp . '</p>' : '' );
                        }
                        $schema .= $this->getSchema( $post->ID, $title, $tmp );
                        $last_anchor = $letter;
                    }
                    if ( !$hide_accordion ){
                        $accordion .= '[/collapsibles]';

                        if ($registerstyle == 'a-z'){
                            $content .= $this->createAZ( $aLetters );
                        }

                        $content .= do_shortcode( $accordion );
                    }
                }
            }
        } 
        if ( $schema ){
           $content .= RRZE_SCHEMA_START . $schema . RRZE_SCHEMA_END;
        }

        // 2020-07-30 THIS IS NOT IN USE because f.e. [glossary register="category"] led to errors ("TypeError: e.$slides is null slick.min.js" and "TypeError: can't access property "add"" ) as glossary can have >1 category and so equal sliders would be returned in output which leads to JS errors that avoid accordions to work properly
        // => sliders are not syncable / this info is provided to the user during Sync and in Logfile
        // check if theme 'FAU-Einrichtungen' and [gallery ...] is in use
        // if ( ( wp_get_theme()->Name == 'FAU-Einrichtungen' ) && ( strpos( $content, 'slider') !== false ) ) {
        //     wp_enqueue_script( 'fau-js-heroslider' );
        // }

        // $content = '<div id="myBlock" class="fau-glossary' . ( $color ? ' ' . $color . ' ' : '' ) . ( isset( $additional_class) ? $additional_class : '' ) . '">' . $content . '</div>';
        $content = '<div class="fau-glossary' . ( $color ? ' ' . $color . ' ' : '' ) . ( isset( $additional_class) ? $additional_class : '' ) . '">' . $content . '</div>';
        wp_enqueue_style('rrze-glossary-style');
        return $content;
    }

    public function sortIt( &$arr ){
        uasort( $arr, function($a, $b) {
            return strtolower( $a ) <=> strtolower( $b );
        } );
    }

    public function setMCEConfig(){
        $shortcode = '';
        foreach($this->settings as $att => $details){
            if ($att != 'block'){
                $shortcode .= ' ' . $att . '=""';
            }
        }
        $shortcode = '[' . $this->pluginname . ' ' . $shortcode . ']';
        ?>
        <script type='text/javascript'>
            tmp = [{
                'name': <?php echo json_encode($this->pluginname); ?>,
                'title': <?php echo json_encode($this->settings['block']['title']); ?>,
                'icon': <?php echo json_encode($this->settings['block']['tinymce_icon']); ?>,
                'shortcode': <?php echo json_encode($shortcode); ?>,
            }];
            phpvar = (typeof phpvar === 'undefined' ? tmp : phpvar.concat(tmp)); 
        </script> 
        <?php        
    }

    public function addMCEButtons($pluginArray){
        if (current_user_can('edit_posts') &&  current_user_can('edit_pages')) {
            $pluginArray['rrze_shortcode'] = plugins_url('../assets/js/tinymce-shortcodes.js', plugin_basename(__FILE__));
        }
        return $pluginArray;
    }
}




