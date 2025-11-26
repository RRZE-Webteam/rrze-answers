<?php

namespace RRZE\Answers\Common\API;

defined('ABSPATH') || exit;

/**
 * REST API for the 'rrze_faq' and 'rrze_glossary' object type
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

        // allow or forbid API for others to import 
        add_filter('rest_authentication_errors', [$this, 'activateAPI'], 10, 1);
    }


    public function activateAPI($result)
    {
        if (!empty($result) || !(defined('REST_REQUEST') && REST_REQUEST)) {
            return $result;
        }

        $route = (string) ($GLOBALS['wp']->query_vars['rest_route'] ?? '');
        $route = ltrim($route, '/');

        if (preg_match('#^wp/v2/([^/]+)#', $route, $m)) {
            $base = $m[1];

            $post_type = get_post_type_object($base) ? $base : null;
            if (!$post_type) {
                foreach (get_post_types([], 'objects') as $ptype => $obj) {
                    $rest_base = $obj->rest_base ?: $ptype;
                    if ($rest_base === $base) {
                        $post_type = $ptype;
                        break;
                    }
                }
            }

            if (in_array($post_type, ['rrze_faq', 'rrze_placeholder', 'rrze_glossary'], true)) {
                $obj = get_post_type_object($post_type);
                if (is_user_logged_in() && $obj && current_user_can($obj->cap->edit_posts)) {
                    return $result;
                }

                $opts = (array) get_option('rrze-answers');
                $active = $opts['api_active_' . $post_type] ?? '';

                if ($active !== '1') {
                    return new \WP_Error(
                        'forbidden',
                        sprintf(__('API is deactivated for %s. Contact website owner %s', 'rrze-answers'), $post_type, '[email]'),
                        ['status' => 403]
                    );
                }
            }
        }

        return $result;
    }

    public function getMyPostMeta($object, $attr)
    {
        return get_post_meta($object['id'], $attr, true);
    }

    // make API deliver source and lang for placeholders
    public function createPostMeta()
    {
        $fields = array(
            'source',
            'lang',
            'placeholder',
            'titleLang',
            'remoteID',
            'remoteChanged'
        );

        foreach ($fields as $field) {
            register_rest_field('rrze_placeholder', $field, array(
                'get_callback' => [$this, 'getMyPostMeta'],
                'schema' => null,
            ));
        }
    }

    public function addFilters()
    {
        add_filter('rest_placeholder_query', [$this, 'addFilterParam'], 10, 2);
    }

    /**
     * Get the meta 'source' of a post object type
     *
     * @param array $object
     * @return string
     */
    public function getPostSource($object)
    {
        return get_post_meta($object['id'], 'source', true);
    }

    /**
     * Get the meta 'lang' of a post object type
     *
     * @param array $object
     * @return string
     */
    public function getPostLang($object)
    {
        return get_post_meta($object['id'], 'lang', true);
    }

    /**
     * Get the meta 'remoteID' of a post object type
     *
     * @param array $object
     * @return string
     */
    public function getPostRemoteID($object)
    {
        return get_post_meta($object['id'], 'remoteID', true);
    }

    /**
     * Get the meta 'remoteChanged' of a post object type
     *
     * @param array $object
     * @return string
     */
    public function getPostRemoteChanged($object)
    {
        return get_post_meta($object['id'], 'remoteChanged', true);
    }

    /**
     * Registers meta fields of the 'rrze_faq' and 'rrze_glossary' object types
     */
    public function registerPostMetaRestFields()
    {
        $post_types = array('rrze_faq', 'rrze_glossary');

        foreach ($post_types as $post_type) {
            // Registers the 'source' meta field
            register_rest_field($post_type, 'source', array(
                'get_callback' => [$this, 'getPostSource'],
                'schema' => null,
            ));
            // Registers the 'lang' meta field
            register_rest_field($post_type, 'lang', array(
                'get_callback' => [$this, 'getPostLang'],
                'schema' => null,
            ));
            // Registers the 'remoteID' meta field
            register_rest_field($post_type, 'remoteID', array(
                'get_callback' => [$this, 'getPostRemoteID'],
                'schema' => null,
            ));
            // Registers the 'remoteChanged' meta field
            register_rest_field($post_type, 'remoteChanged', array(
                'get_callback' => [$this, 'getPostRemoteChanged'],
                'schema' => null,
            ));
        }
    }

    /**
     * Add filters to the REST API query
     */
    public function addRestQueryFilters()
    {
        // Add filter parameters to the post type queries
        $post_types = array('rrze_faq', 'rrze_glossary');
        foreach ($post_types as $post_type) {
            add_filter('rest_' . $post_type . '_query', [$this, 'addFilterParam'], 10, 2);
        }

        // Add filter parameters to the categories queries
        $tax_queries = array(
            'rrze_faq_category',
            'rrze_faq_tag',
            'rrze_glossary_category',
            'rrze_glossary_tag',
        );

        foreach ($tax_queries as $taxonomy) {
            add_filter('rest_' . $taxonomy . '_query', [$this, 'addFilterParam'], 10, 2);
        }
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
     * Get the terms names of the category taxonomy for FAQ or Glossary
     *
     * @param array $object
     * @return array
     */
    public function getCategories($object)
    {
        // Object type is available in the REST object array
        $post_type = isset($object['type']) ? $object['type'] : '';

        // Fallback to FAQ tax if type is unknown
        $taxonomy = 'rrze_faq_category';
        if ($post_type === 'rrze_glossary') {
            $taxonomy = 'rrze_glossary_category';
        }

        $cats = wp_get_post_terms($object['id'], $taxonomy, array('fields' => 'names'));
        return $cats;
    }

    /**
     * Get the children term names of the current taxonomy
     *
     * @param array $term
     * @return array
     */
    public function getChildrenCategories($term)
    {
        // Use the taxonomy of the term to make this work for FAQ and Glossary
        $taxonomy = isset($term['taxonomy']) ? $term['taxonomy'] : 'rrze_faq_category';

        $children = get_terms(
            array(
                'taxonomy' => $taxonomy,
                'parent'   => $term['id'],
            )
        );
        $aRet = [];
        foreach ($children as $child) {
            $aRet[] = $child->name;
        }
        return $aRet;
    }

    /**
     * Get the terms names of the tag taxonomy for FAQ or Glossary
     *
     * @param array $object
     * @return array
     */
    public function getTags($object)
    {
        $post_type = isset($object['type']) ? $object['type'] : '';

        $taxonomy = 'rrze_faq_tag';
        if ($post_type === 'rrze_glossary') {
            $taxonomy = 'rrze_glossary_tag';
        }

        return wp_get_post_terms($object['id'], $taxonomy, array('fields' => 'names'));
    }

    /**
     * Get the term meta 'source'
     *
     * @param array $object
     * @return string
     */
    public function getTermSource($object)
    {
        return get_term_meta($object['id'], 'source', true);
    }

    /**
     * Get the term meta 'lang'
     *
     * @param array $object
     * @return string
     */
    public function getTermLang($object)
    {
        return get_term_meta($object['id'], 'lang', true);
    }

    /**
     * Registers the taxonomies fields for the 'rrze_faq' and 'rrze_glossary' object types
     */
    public function registerTaxRestFields()
    {
        // FAQ categories and tags
        register_rest_field(
            'rrze_faq',
            'rrze_faq_category',
            array(
                'get_callback'    => [$this, 'getCategories'],
                'update_callback' => null,
                'schema'          => null,
            )
        );

        register_rest_field(
            'rrze_faq',
            'rrze_faq_tag',
            array(
                'get_callback'    => [$this, 'getTags'],
                'update_callback' => null,
                'schema'          => null,
            )
        );

        // Glossary categories and tags
        register_rest_field(
            'rrze_glossary',
            'rrze_glossary_category',
            array(
                'get_callback'    => [$this, 'getCategories'],
                'update_callback' => null,
                'schema'          => null,
            )
        );

        register_rest_field(
            'rrze_glossary',
            'rrze_glossary_tag',
            array(
                'get_callback'    => [$this, 'getTags'],
                'update_callback' => null,
                'schema'          => null,
            )
        );

        // Registers the 'source' and 'lang' meta fields for all FAQ/Glossary taxonomies
        $fields = array(
            'rrze_faq_category',
            'rrze_faq_tag',
            'rrze_glossary_category',
            'rrze_glossary_tag',
        );

        foreach ($fields as $field) {
            // Registers the 'source' meta field
            register_rest_field($field, 'source', array(
                'get_callback' => [$this, 'getTermSource'],
                'schema'       => null,
            ));
            // Registers the 'lang' meta field
            register_rest_field($field, 'lang', array(
                'get_callback' => [$this, 'getTermLang'],
                'schema'       => null,
            ));
        }
    }

    /**
     * Registers the taxonomy children field for FAQ and Glossary categories
     */
    public function registerTaxChildrenRestField()
    {
        // Make the same callback work for FAQ and Glossary categories
        $category_taxonomies = array(
            'rrze_faq_category',
            'rrze_glossary_category',
        );

        foreach ($category_taxonomies as $taxonomy) {
            register_rest_field(
                $taxonomy,
                'children',
                array(
                    'get_callback'    => [$this, 'getChildrenCategories'],
                    'update_callback' => null,
                    'schema'          => null,
                )
            );
        }
    }
}
