<?php

namespace RRZE\Answers\Common\API;

defined('ABSPATH') || exit;

// define('ENDPOINT', 'wp-json/wp/v2/faq');

use RRZE\Answers\Common\Config;


class SyncAPI
{
    private $aAllCats = array();
    

    public function __construct()
    {
            }

    public function setDomain($shortname, $url, $domains)
    {
        // returns array('status' => TRUE, 'ret' => array(cleanShortname, cleanUrl)
        // on error returns array('status' => FALSE, 'ret' => error-message)
        try {
            $aRet = array('status' => false, 'ret' => '');
            $cleanUrl = trailingslashit(preg_replace("/^((http|https):\/\/)?/i", "https://", $url));
            $cleanShortname = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $shortname));

            if (in_array($cleanUrl, $domains)) {
                $aRet['ret'] = $url . __(' is already in use.', 'rrze-answers');
                return $aRet;
            } elseif (array_key_exists($cleanShortname, $domains)) {
                $aRet['ret'] = $cleanShortname . __(' is already in use.', 'rrze-answers');
                return $aRet;
            } else {
                $request = $this->remoteGet($cleanUrl . ENDPOINT . '?per_page=1');
                $status_code = wp_remote_retrieve_response_code($request);

                if ($status_code != '200') {
                    $aRet['ret'] = $cleanUrl . __(' is not valid.', 'rrze-answers');
                    return $aRet;
                } else {
                    $content = json_decode(wp_remote_retrieve_body($request), true);

                    if ($content) {
                        $cleanUrl = substr($content[0]['link'], 0, strpos($content[0]['link'], '/faq')) . '/';
                    } else {
                        $aRet['ret'] = $cleanUrl . __(' is not valid.', 'rrze-answers');
                        return $aRet;
                    }
                }
            }

            $aRet['status'] = true;
            $aRet['ret'] = array('cleanShortname' => $cleanShortname, 'cleanUrl' => $cleanUrl);
            return $aRet;
        } catch (CustomException $e) {
            return new \WP_Error('setDomain_error', __('Error in setDomain().', 'rrze-answers'));
        }
    }

    protected function isRegisteredDomain(&$url)
    {
        return in_array($url, $this->getDomains());
    }

    public function getDomains()
    {
        $domains = array();
        $options = get_option('rrze-answers');
        if (isset($options['registeredDomains'])) {
            foreach ($options['registeredDomains'] as $shortname => $url) {
                $domains[$shortname] = $url;
            }
        }
        asort($domains);
        return $domains;
    }

    protected function getTaxonomies($url, $field, &$filter)
    {
        $aRet = array();
        $url .= ENDPOINT . '_' . $field;
        $slug = ($filter ? '&slug=' . $filter : '');
        $page = 1;

        try {
            do {
                $request = $this->remoteGet($url . '?page=' . $page . $slug);
                $status_code = wp_remote_retrieve_response_code($request);
                if ($status_code == 200) {
                    $entries = json_decode(wp_remote_retrieve_body($request), true);
                    if (!empty($entries)) {
                        foreach ($entries as $entry) {
                            if ($entry['source'] == 'website') {
                                if ($entry['children']) {
                                    foreach ($entry['children'] as $childname) {
                                        $aRet[$entry['name']][$childname] = array();
                                    }
                                } else {
                                    $aRet[$entry['name']] = array();
                                }
                            }
                        }
                        foreach ($aRet as $name => $aChildren) {
                            foreach ($aChildren as $childname => $val) {
                                if (isset($aRet[$childname])) {
                                    $aRet[$name][$childname] = $aRet[$childname];
                                }
                            }
                        }
                    }
                }
                $page++;
            } while (($status_code == 200) && (!empty($entries)));
            return $aRet;
        } catch (CustomException $e) {
            return new \WP_Error('getTaxonomies_error', __('Error in getTaxonomies().', 'rrze-answers'));
        }
    }

    public function sortIt(&$arr)
    {
        uasort($arr, function ($a, $b) {
            return strtolower($a) <=> strtolower($b);
        });
    }

    public function deleteTaxonomies($source, $field)
    {
        try {
            $args = array(
                'hide_empty' => false,
                'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    array(
                        'key' => 'source',
                        'value' => $source,
                        'compare' => '=',
                    ),
                ),
                'taxonomy' => $field,
                'fields' => 'ids',
            );
            $terms = get_terms($args);
            foreach ($terms as $ID) {
                wp_delete_term($ID, $field);
            }
        } catch (CustomException $e) {
            return new \WP_Error('deleteTaxonomies_error', __('Error in deleteTaxonomies().', 'rrze-answers'));
        }
    }

    public function deleteCategories($source)
    {
        $this->deleteTaxonomies($source, 'rrze_faq_category');
    }

    public function deleteTags($source)
    {
        $this->deleteTaxonomies($source, 'rrze_faq_tag');
    }

    protected function setCategories(&$aCategories, &$shortname)
    {
        try {
            $aTmp = $aCategories;
            foreach ($aTmp as $name => $aDetails) {
                $term = term_exists($name, 'rrze_faq_category');
                if (!$term) {
                    $term = wp_insert_term($name, 'rrze_faq_category');
                }
                update_term_meta($term['term_id'], 'source', $shortname);
                foreach ($aDetails as $childname => $tmp) {
                    $childterm = term_exists($childname, 'rrze_faq_category');
                    if (!$childterm) {
                        $childterm = wp_insert_term($childname, 'rrze_faq_category', array('parent' => $term['term_id']));
                        update_term_meta($childterm['term_id'], 'source', $shortname);
                    }
                }
                if ($aDetails) {
                    $aTmp = $aDetails;
                }
            }
        } catch (CustomException $e) {
            return new \WP_Error('setCategories_error', __('Error in setCategories().', 'rrze-answers'));
        }
    }

    public function sortAllCats(&$cats, &$into)
    {
        foreach ($cats as $ID => $aDetails) {
            $into[$ID]['slug'] = $aDetails['slug'];
            $into[$ID]['name'] = $aDetails['name'];
            if ($aDetails['parentID']) {
                $parentID = $aDetails['parentID'];
                $into[$parentID][$ID]['slug'] = $aDetails['slug'];
                $into[$parentID][$ID]['name'] = $aDetails['name'];
            }
            unset($cats[$parentID]);
        }
        $this->sortAllCats($cats, $into);
    }

    public function sortCats(array &$cats, array &$into, $parentID = 0, $prefix = '')
    {
        try {
            $prefix .= ($parentID ? '-' : '');
            foreach ($cats as $i => $cat) {
                if ($cat->parent == $parentID) {
                    $into[$cat->term_id] = $cat;
                    unset($cats[$i]);
                }
                $this->aAllCats[$cat->term_id]['parentID'] = $cat->parent;
                $this->aAllCats[$cat->term_id]['slug'] = $cat->slug;
                $this->aAllCats[$cat->term_id]['name'] = str_replace('~', '&nbsp;', str_pad(ltrim($prefix . ' ' . $cat->name), 100, '~'));
            }
            foreach ($into as $topCat) {
                $topCat->children = array();
                $this->sortCats($cats, $topCat->children, $topCat->term_id, $prefix);
            }
            if (!$cats) {
                foreach ($this->aAllCats as $ID => $aDetails) {
                    if ($aDetails['parentID']) {
                        $this->aAllCats[$aDetails['parentID']]['children'][$ID] = $this->aAllCats[$ID];
                    }
                }
            }
        } catch (CustomException $e) {
            return new \WP_Error('sortCats_error', __('Error in sortCats().', 'rrze-answers'));
        }
    }

    public function cleanCats()
    {
        foreach ($this->aAllCats as $ID => $aDetails) {
            if ($aDetails['parentID']) {
                unset($this->aAllCats[$ID]);
            }
        }
    }

    public function getSlugNameCats(&$cats, &$into)
    {
        foreach ($cats as $i => $cat) {
            $into[$cat['slug']] = $cat['name'];
            if (isset($cat['children'])) {
                $this->getSlugNameCats($cat['children'], $into);
            }
            unset($cats[$i]);
        }
    }

    public function getCategories($url, $shortname, $categories = '')
    {
        $aRet = array();
        $aCategories = $this->getTaxonomies($url, 'rrze_faq_category', $categories);
        $this->setCategories($aCategories, $shortname);
        $categories = get_terms(array(
            'taxonomy' => 'rrze_faq_category',
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key' => 'source',
                    'value' => $shortname,
                )
            ),
            'hide_empty' => false,
        ));
        $categoryHierarchy = array();
        $this->sortCats($categories, $categoryHierarchy);
        $this->cleanCats();
        $this->getSlugNameCats($this->aAllCats, $aRet);
        return $aRet;
    }

    public function deleteFAQ($source)
    {
        // deletes all FAQ by source
        $iDel = 0;
        $allFAQ = get_posts(array('post_type' => 'rrze_faq', 'meta_key' => 'source', 'meta_value' => $source, 'numberposts' => -1)); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        foreach ($allFAQ as $faq) {
            wp_delete_post($faq->ID, true);
            $iDel++;
        }
        return $iDel;
    }

    protected function absoluteUrl($txt, $baseUrl)
    {
        try {
            // converts relative URLs to absolute ones
            $needles = array('href="', 'src="', 'background="');
            $newTxt = '';
            if (substr($baseUrl, -1) != '/') {
                $baseUrl .= '/';
            }
            $newBaseUrl = $baseUrl;
            $baseUrlParts = wp_parse_url($baseUrl);
            foreach ($needles as $needle) {
                while ($pos = strpos($txt, $needle)) {
                    $pos += strlen($needle);
                    if (substr($txt, $pos, 7) != 'http://' && substr($txt, $pos, 8) != 'https://' && substr($txt, $pos, 6) != 'ftp://' && substr($txt, $pos, 7) != 'mailto:') {
                        if (substr($txt, $pos, 1) == '/') {
                            $newBaseUrl = $baseUrlParts['scheme'] . '://' . $baseUrlParts['host'];
                        }
                        $newTxt .= substr($txt, 0, $pos) . $newBaseUrl;
                    } else {
                        $newTxt .= substr($txt, 0, $pos);
                    }
                    $txt = substr($txt, $pos);
                }
                $txt = $newTxt . $txt;
                $newTxt = '';
            }
            // convert all elements of srcset, too
            $needle = 'srcset="';
            while ($pos = strpos($txt, $needle, $pos)) {
                $pos += strlen($needle);
                $len = strpos($txt, '"', $pos) - $pos;
                $srcset = substr($txt, $pos, $len);
                $aSrcset = explode(',', $srcset);
                $aNewSrcset = array();
                foreach ($aSrcset as $src) {
                    $src = trim($src);
                    if (substr($src, 0, 1) == '/') {
                        $aNewSrcset[] = $newBaseUrl . $src;
                    }
                }
                $newSrcset = implode(', ', $aNewSrcset);
                $txt = str_replace($srcset, $newSrcset, $txt);
            }
            return $txt;
        } catch (CustomException $e) {
            return new \WP_Error('absoluteUrl_error', __('Error in absoluteUrl().', 'rrze-answers'));
        }
    }

    protected function getFAQ(&$url, &$categories)
    {
        try {
            $faqs = array();
            $aCategoryRelation = array();
            $filter = '&filter[faq_category]=' . $categories;
            $page = 1;

            do {
                $request = $this->remoteGet($url . ENDPOINT . '?page=' . $page . $filter);
                $status_code = wp_remote_retrieve_response_code($request);
                if ($status_code == 200) {
                    $entries = json_decode(wp_remote_retrieve_body($request), true);
                    if (!empty($entries)) {
                        if (!isset($entries[0])) {
                            $entries = array($entries);
                        }
                        foreach ($entries as $entry) {
                            if ($entry['source'] == 'website') {
                                $content = $entry['content']['rendered'];
                                $content = $this->absoluteUrl($content, $url);

                                $faqs[$entry['id']] = array(
                                    'id' => $entry['id'],
                                    'title' => $entry['title']['rendered'],
                                    'content' => $content,
                                    'lang' => $entry['lang'],
                                    'rrze_faq_category' => $entry['rrze_faq_category'],
                                    'remoteID' => $entry['remoteID'],
                                    'remoteChanged' => $entry['remoteChanged'],
                                );
                                $sTag = '';
                                foreach ($entry['rrze_faq_tag'] as $tag) {
                                    $sTag .= $tag . ',';
                                }
                                $faqs[$entry['id']]['rrze_faq_tag'] = trim($sTag, ',');
                                $faqs[$entry['id']]['URLhasSlider'] = ((strpos($content, 'slider') !== false) ? $entry['link'] : false); // we cannot handle sliders, see note in Shortcode.php shortcodeOutput()
                            }
                        }
                    }
                }
                $page++;
            } while (($status_code == 200) && (!empty($entries)));

            return $faqs;
        } catch (CustomException $e) {
            return new \WP_Error('getFAQ_error', __('Error in getFAQ().', 'rrze-answers'));
        }
    }

    public function setTags($terms, $shortname)
    {
        try {
            if ($terms) {
                $aTerms = explode(',', $terms);
                foreach ($aTerms as $name) {
                    if ($name) {
                        $term = term_exists($name, 'rrze_faq_tag');
                        if (!$term) {
                            $term = wp_insert_term($name, 'rrze_faq_tag');
                            update_term_meta($term['term_id'], 'source', $shortname);
                        }
                    }
                }
            }
        } catch (CustomException $e) {
            return new \WP_Error('setTags_error', __('Error in setTags().', 'rrze-answers'));
        }
    }

    public function getFAQRemoteIDs($source)
    {
        try {
            $aRet = array();
            $allFAQ = get_posts(array('post_type' => 'rrze_faq', 'meta_key' => 'source', 'meta_value' => $source, 'fields' => 'ids', 'numberposts' => -1));// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            foreach ($allFAQ as $postID) {
                $remoteID = get_post_meta($postID, 'remoteID', true);
                $remoteChanged = get_post_meta($postID, 'remoteChanged', true);
                $aRet[$remoteID] = array(
                    'postID' => $postID,
                    'remoteChanged' => $remoteChanged,
                );
            }
            return $aRet;
        } catch (CustomException $e) {
            return new \WP_Error('getFAQRemoteIDs_error', __('Error in getFAQRemoteIDs().', 'rrze-answers'));
        }
    }

    public function setFAQ($url, $categories, $shortname)
    {
        try {
            $iNew = 0;
            $iUpdated = 0;
            $iDeleted = 0;
            $aURLhasSlider = array();

            // get all remoteIDs of stored FAQ to this source ( key = remoteID, value = postID )
            $aRemoteIDs = $this->getFAQRemoteIDs($shortname);

            // $this->deleteTags( $shortname );
            // $this->deleteCategories( $shortname );
            // $this->getCategories( $url, $shortname );

            // get all FAQ
            $aFaq = $this->getFAQ($url, $categories);

            // set FAQ
            foreach ($aFaq as $faq) {
                $this->setTags($faq['rrze_faq_tag'], $shortname);

                $aCategoryIDs = array();
                foreach ($faq['rrze_faq_category'] as $name) {
                    $term = get_term_by('name', $name, 'rrze_faq_category');
                    $aCategoryIDs[] = $term->term_id;
                }

                if ($faq['URLhasSlider']) {
                    $aURLhasSlider[] = $faq['URLhasSlider'];
                } else {
                    if (isset($aRemoteIDs[$faq['remoteID']])) {
                        if ($aRemoteIDs[$faq['remoteID']]['remoteChanged'] < $faq['remoteChanged']) {
                            // update FAQ
                            $post_id = wp_update_post(array(
                                'ID' => $aRemoteIDs[$faq['remoteID']]['postID'],
                                'post_name' => sanitize_title($faq['title']),
                                'post_title' => $faq['title'],
                                'post_content' => $faq['content'],
                                'meta_input' => array(
                                    'source' => $shortname,
                                    'lang' => $faq['lang'],
                                    'remoteID' => $faq['remoteID'],
                                ),
                                'tax_input' => array(
                                    'rrze_faq_category' => $aCategoryIDs,
                                    'rrze_faq_tag' => $faq['rrze_faq_tag'],
                                ),
                            ));
                            $iUpdated++;
                        }
                        unset($aRemoteIDs[$faq['remoteID']]);
                    } else {
                        // insert FAQ
                        $post_id = wp_insert_post(array(
                            'post_type' => 'rrze_faq',
                            'post_name' => sanitize_title($faq['title']),
                            'post_title' => $faq['title'],
                            'post_content' => $faq['content'],
                            'comment_status' => 'closed',
                            'ping_status' => 'closed',
                            'post_status' => 'publish',
                            'meta_input' => array(
                                'source' => $shortname,
                                'lang' => $faq['lang'],
                                'remoteID' => $faq['id'],
                                'remoteChanged' => $faq['remoteChanged'],
                                'sortfield' => '',
                            ),
                            'tax_input' => array(
                                'rrze_faq_category' => $aCategoryIDs,
                                'rrze_faq_tag' => $faq['rrze_faq_tag'],
                            ),
                        ));
                        $iNew++;
                    }
                }
            }

            // delete all other FAQ to this source
            foreach ($aRemoteIDs as $remoteID => $aDetails) {
                wp_delete_post($aDetails['postID'], true);
                $iDeleted++;
            }

            return array(
                'iNew' => $iNew,
                'iUpdated' => $iUpdated,
                'iDeleted' => $iDeleted,
                'URLhasSlider' => $aURLhasSlider,
            );
        } catch (CustomException $e) {
            return new \WP_Error('setFAQ_error', __('Error in setFAQ().', 'rrze-answers'));
        }
    }

    /**
     * Get remote content
     * 
     * @param string $url
     * @param array $args
     * @param bool $safe
     * @return mixed
     */
    private function remoteGet(string $url, array $args = [], bool $safe = true)
    {
        try {
            if (!$args) {
                $args = [
                    'sslverify' => defined('WP_DEBUG') && WP_DEBUG ? false : true
                ];
            }
            if ($safe) {
                return wp_safe_remote_get($url, $args);
            } else {
                return wp_remote_get($url, $args);
            }
        } catch (CustomException $e) {
            return new \WP_Error('remoteGet_error', __('Error in remoteGet().', 'rrze-answers'));
        }
    }
}
