<?php

namespace RRZE\Answers\Common\API;

defined('ABSPATH') || exit;

/**
 * REST API for the 'rrze_faq' object type
 */
class RESTAPI
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerPostMetaRestFields']);
        add_action('rest_api_init', [$this, 'registerTaxRestFields']);
        add_action('rest_api_init', [$this, 'registerTaxChildrenRestField']);
        add_action('rest_api_init', [$this, 'addRestQueryFilters']);

        add_action('rest_api_init', [$this, 'createPostMeta']);
        add_action('rest_api_init', [$this, 'addFilters']);

    }


    public function getMyPostMeta($object, $attr)
    {
        return get_post_meta($object['id'], $attr, TRUE);
    }

    // make API deliver source and lang for synonyms
    public function createPostMeta()
    {
        $fields = array(
            'source',
            'lang',
            'synonym',
            'titleLang',
            'remoteID',
            'remoteChanged'
        );

        foreach ($fields as $field) {
            register_rest_field('rrze_synonym', $field, array(
                'get_callback' => [$this, 'getMyPostMeta'],
                'schema' => null,
            ));
        }
    }

    public function addFilters()
    {
        add_filter('rest_synonym_query', [$this, 'addFilterParam'], 10, 2);
    }

    /**
     * Get the meta 'source' of a 'rrze_faq' object type
     *
     * @param array $object
     * @return string
     */
    public function getPostSource($object)
    {
        return get_post_meta($object['id'], 'source', true);
    }

    /**
     * Get the meta 'lang' of a 'rrze_faq' object type
     *
     * @param array $object
     * @return string
     */
    public function getPostLang($object)
    {
        return get_post_meta($object['id'], 'lang', true);
    }

    /**
     * Get the meta 'remoteID' of a 'rrze_faq' object type
     *
     * @param array $object
     * @return string
     */
    public function getPostRemoteID($object)
    {
        return get_post_meta($object['id'], 'remoteID', true);
    }

    /**
     * Get the meta 'remoteChanged' of a 'rrze_faq' object type
     *
     * @param array $object
     * @return string
     */
    public function getPostRemoteChanged($object)
    {
        return get_post_meta($object['id'], 'remoteChanged', true);
    }

    /**
     * Registers meta fields of a 'rrze_faq' object type
     */
    public function registerPostMetaRestFields()
    {
        // Registers the 'source' meta field for the 'rrze_faq' object type
        register_rest_field('rrze_faq', 'source', array(
            'get_callback' => [$this, 'getPostSource'],
            'schema' => null,
        ));
        // Registers the 'lang' meta field for the 'rrze_faq' object type
        register_rest_field('rrze_faq', 'lang', array(
            'get_callback' => [$this, 'getPostLang'],
            'schema' => null,
        ));
        // Registers the 'remoteID' meta field for the 'rrze_faq' object type
        register_rest_field('rrze_faq', 'remoteID', array(
            'get_callback' => [$this, 'getPostRemoteID'],
            'schema' => null,
        ));
        // Registers the 'remoteChanged' meta field for the 'rrze_faq' object type
        register_rest_field('rrze_faq', 'remoteChanged', array(
            'get_callback' => [$this, 'getPostRemoteChanged'],
            'schema' => null,
        ));
    }

    /**
     * Add filters to the REST API query
     */
    public function addRestQueryFilters()
    {
        // Add filter parameters to the object query
        add_filter('rest_rrze_faq_query', [$this, 'addFilterParam'], 10, 2);
        // Add filter parameters to the categories query
        add_filter('rest_rrze_faq_category_query', [$this, 'addFilterParam'], 10, 2);
        // Add filter parameters to the tags query
        add_filter('rest_rrze_faq_tag_query', [$this, 'addFilterParam'], 10, 2);
    }

    /**
     * Add filter parameters to the query
     *
     * @param array $args
     * @param array $request
     * @return array
     */
    public function addFilterParam($args, $request)
    {
        if (empty($request['filter']) || !is_array($request['filter'])) {
            return $args;
        }
        global $wp;
        $filter = $request['filter'];

        $vars = apply_filters('query_vars', $wp->public_query_vars);
        foreach ($vars as $var) {
            if (isset($filter[$var])) {
                $args[$var] = $filter[$var];
            }
        }
        return $args;
    }

    /**
     * Get the terms names of the 'rrze_faq_category' taxonomy
     *
     * @param array $object
     * @return array
     */
    public function getCategories($object)
    {
        $cats = wp_get_post_terms($object['id'], 'rrze_faq_category', array('fields' => 'names'));
        return $cats;
    }

    /**
     * Get the children terms names of the 'rrze_faq_category' taxonomy
     *
     * @param array $term
     * @return array
     */
    public function getChildrenCategories($term)
    {
        $children = get_terms(
            array(
                'taxonomy' => 'rrze_faq_category',
                'parent' => $term['id'],
            )
        );
        $aRet = array();
        foreach ($children as $child) {
            $aRet[] = $child->name;
        }
        return $aRet;
    }

    /**
     * Get the terms names of the 'rrze_faq_tag' taxonomy
     *
     * @param array $object
     * @return array
     */
    public function getTags($object)
    {
        return wp_get_post_terms($object['id'], 'rrze_faq_tag', array('fields' => 'names'));
    }

    /**
     * Get the term meta 'source' of a 'rrze_faq' object type
     *
     * @param array $object
     * @return string
     */
    public function getTermSource($object)
    {
        return get_term_meta($object['id'], 'source', true);
    }

    /**
     * Get the term meta 'lang' of a 'rrze_faq' object type
     *
     * @param array $object
     * @return string
     */
    public function getTermLang($object)
    {
        return get_term_meta($object['id'], 'lang', true);
    }

    /**
     * Registers the taxonomies fields for the 'rrze_faq' object type
     */
    public function registerTaxRestFields()
    {
        // Registers the 'source' and 'lang' meta fields for the 'rrze_faq_category' and 'rrze_faq_tag' taxonomies
        $fields = array('rrze_faq_category', 'rrze_faq_tag');
        foreach ($fields as $field) {
            // Registers the 'source' meta field
            register_rest_field($field, 'source', array(
                'get_callback' => [$this, 'getTermSource'],
                'schema' => null,
            ));
            // Registers the 'lang' meta field
            register_rest_field($field, 'lang', array(
                'get_callback' => [$this, 'getTermLang'],
                'schema' => null,
            ));
        }
    }

    /**
     * Registers the taxonomy children field for the 'rrze_faq_category' taxonomy
     */
    public function registerTaxChildrenRestField()
    {
        register_rest_field(
            'rrze_faq_category',
            'children',
            array(
                'get_callback' => [$this, 'getChildrenCategories'],
                'update_callback' => null,
                'schema' => null,
            )
        );
    }
}
