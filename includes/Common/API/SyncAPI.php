<?php

namespace RRZE\Answers\Common\API;

use RRZE\Answers\Common\CustomException;

defined('ABSPATH') || exit;


class SyncAPI
{
    private $aAllCats = [];


    public function __construct()
    {
    }

    public function getTaxonomies($url, $field, &$filter)
    {
        $cacheKey = 'rrze_answers_tax_v2_' . md5($url . '|' . $field . '|' . (string) $filter);
        $cached = get_transient($cacheKey);

        if (!empty($cached)) {
            return $cached;
        }

        // Return flat map: slug => name
        $aRet = [];
        $slug = ($filter ? '&slug=' . $filter : '');
        $page = 1;

        try {
            do {
                $request = $this->remoteGet($url . '/' . ENDPOINT . $field . '?page=' . $page . $slug);

                if (is_wp_error($request)) {
                    break;
                }

                $status_code = wp_remote_retrieve_response_code($request);

                if ($status_code === 403) {
                    return new \WP_Error('remote_forbidden', __('Import not allowed by source site.', 'rrze-answers'));
                }

                if ($status_code !== 200) {
                    break;
                }

                $entries = json_decode(wp_remote_retrieve_body($request), true);

                if (empty($entries)) {
                    break;
                }

                foreach ($entries as $entry) {
                    if (!isset($entry['source']) || $entry['source'] !== 'website') {
                        continue;
                    }

                    $name = $entry['name'] ?? null;
                    $term_slug = $entry['slug'] ?? null;
                    if (!$name || !$term_slug) {
                        continue;
                    }

                    $aRet[$term_slug] = $name;
                }

                $page++;
            } while (true);

            // Cache the result for 1 hour
            set_transient($cacheKey, $aRet, HOUR_IN_SECONDS);

            return $aRet;
        } catch (CustomException $e) {
            return new \WP_Error('getTaxonomies_error', __('Error in getTaxonomies().', 'rrze-answers'));
        }
    }

    public function deleteTaxonomies($identifier, $field)
    {
        try {
            $args = array(
                'hide_empty' => false,
                'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    array(
                        'key' => 'source',
                        'value' => $identifier,
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

    public function deleteCategories($identifier, $type)
    {
        $this->deleteTaxonomies($identifier, 'rrze_' . $type . '_category');
    }

    public function deleteTags($identifier, $type)
    {
        $this->deleteTaxonomies($identifier, 'rrze_' . $type . '_tag');
    }

    protected function setCategories(&$aCategories, &$identifier, $type)
    {
        // Array that will collect all term IDs
        $termIds = [];

        try {
            $field = 'rrze_' . $type . '_category';
            // $aTmp = $aCategories;

            // foreach ($aTmp as $name => $aDetails) {
            foreach ($aCategories as $name) {

                // Try to get or create parent term
                $term = term_exists($name, $field);
                if (!$term) {
                    $term = wp_insert_term($name, $field);
                }

                // Add parent term ID to result array
                if (!is_wp_error($term) && isset($term['term_id'])) {
                    $termIds[] = $term['term_id'];
                    update_term_meta($term['term_id'], 'source', $identifier);
                }

                // // Process child terms
                // foreach ($aDetails as $childname => $tmp) {
                //     $childterm = term_exists($childname, $field);
                //     if (!$childterm) {
                //         $childterm = wp_insert_term($childname, $field, [
                //             'parent' => $term['term_id']
                //         ]);
                //     }

                //     // Add child term ID to result array
                //     if (!is_wp_error($childterm) && isset($childterm['term_id'])) {
                //         $termIds[] = $childterm['term_id'];
                //         update_term_meta($childterm['term_id'], 'source', $identifier);
                //     }
                // }

                // if ($aDetails) {
                //     $aTmp = $aDetails;
                // }
            }

            // Return all collected term IDs
            return $termIds;

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
                $topCat->children = [];
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

    // public function getCategories($identifier, $url, $type, $categories = '')
    // {
    //     $aRet = [];
    //     $field = 'rrze_' . $type . '_category';
    //     $aCategories = $this->getTaxonomies($url, $field, $categories);
    //     $this->setCategories($aCategories, $identifier, $type);

    //     $categories = get_terms(array(
    //         'taxonomy' => $field,
    //         'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    //             array(
    //                 'key' => 'source',
    //                 'value' => $identifier,
    //             )
    //         ),
    //         'hide_empty' => false,
    //     ));
    //     $categoryHierarchy = [];
    //     $this->sortCats($categories, $categoryHierarchy);
    //     $this->cleanCats();
    //     $this->getSlugNameCats($this->aAllCats, $aRet);
    //     return $aRet;
    // }

    public function deleteEntries($identifier, $type)
    {
        // deletes all Entries by url
        $iDel = 0;
        $allEntries = get_posts(array('post_type' => 'rrze_' . $type, 'meta_key' => 'source', 'meta_value' => $identifier, 'numberposts' => -1)); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        foreach ($allEntries as $entry) {
            wp_delete_post($entry->ID, true);
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
                $aNewSrcset = [];
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

    protected function getEntries(&$url, &$categories, $type)
    {
        try {
            $ret = [];
            $field_cat = 'rrze_' . $type . '_category';
            $field_tag = 'rrze_' . $type . '_tag';
            $filter = '&filter[rrze_' . $type . '_category]=' . rawurlencode($categories);
            $page = 1;
            $total_pages = null;

            do {
                $request = $this->remoteGet(
                    $url . '/' . ENDPOINT . $type . '?per_page=100&page=' . $page . $filter,
                    [],
                    true,
                    false
                );

                if (is_wp_error($request)) {
                    return $request;
                }

                $status_code = (int) wp_remote_retrieve_response_code($request);

                if ($status_code === 403) {
                    return new \WP_Error('remote_forbidden', __('Import not allowed by source site.', 'rrze-answers'));
                }

                if ($status_code !== 200) {
                    return new \WP_Error(
                        'remote_http_error',
                        sprintf(
                            /* translators: %d: HTTP response status code. */
                            __('The source site returned HTTP status %d. Existing synchronized entries were preserved.', 'rrze-answers'),
                            $status_code
                        )
                    );
                }

                $body = wp_remote_retrieve_body($request);
                $entries = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($entries) || !array_is_list($entries)) {
                    return new \WP_Error(
                        'remote_invalid_response',
                        __('The source site returned an invalid response. Existing synchronized entries were preserved.', 'rrze-answers')
                    );
                }

                $response_total_pages = (int) wp_remote_retrieve_header($request, 'x-wp-totalpages');
                if ($response_total_pages > 0) {
                    if ($total_pages !== null && $total_pages !== $response_total_pages) {
                        return new \WP_Error(
                            'remote_inconsistent_pagination',
                            __('The source content changed while it was being fetched. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    $total_pages = $response_total_pages;
                }

                if (empty($entries)) {
                    if ($total_pages !== null && $page <= $total_pages) {
                        return new \WP_Error(
                            'remote_incomplete_response',
                            __('The source site returned an incomplete response. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    break;
                }

                foreach ($entries as $entry) {
                    if (
                        !is_array($entry)
                        || !array_key_exists('source', $entry)
                        || !isset($entry['id'], $entry['title']['rendered'], $entry['content']['rendered'])
                    ) {
                        return new \WP_Error(
                            'remote_invalid_entry',
                            __('The source site returned an incomplete entry. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    if ($entry['source'] !== 'website') {
                        continue;
                    }

                    $content = $this->absoluteUrl($entry['content']['rendered'], $url);
                    if (is_wp_error($content)) {
                        return $content;
                    }

                    $remote_id = $entry['remoteID'] ?? $entry['id'];
                    $remote_changed = $entry['remoteChanged'] ?? null;

                    if ($remote_id === '' || $remote_changed === null) {
                        return new \WP_Error(
                            'remote_invalid_entry',
                            __('The source site returned an incomplete entry. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    if (isset($ret[$remote_id])) {
                        return new \WP_Error(
                            'remote_duplicate_entry',
                            __('The source site returned duplicate entries. Existing synchronized entries were preserved.', 'rrze-answers')
                        );
                    }

                    $category_names = $this->restTermsToNames($entry[$field_cat] ?? [], $field_cat);
                    $tag_names = $this->restTermsToNames($entry[$field_tag] ?? [], $field_tag);

                    $ret[$remote_id] = array(
                        'id' => $entry['id'],
                        'title' => $entry['title']['rendered'],
                        'content' => $content,
                        'lang' => $entry['lang'] ?? '',
                        $field_cat => $category_names,
                        'remoteID' => $remote_id,
                        'remoteChanged' => $remote_changed,
                        $field_tag => implode(',', $tag_names),
                        'URLhasSlider' => strpos($content, 'slider') !== false
                            ? ($entry['link'] ?? $url)
                            : false,
                    );
                }

                $page++;
            } while ($total_pages === null || $page <= $total_pages);

            return $ret;
        } catch (\Throwable $e) {
            return new \WP_Error('getEntry_error', __('Error in getEntry().', 'rrze-answers'));
        }
    }

    public function setTags($terms, $identifier, $type)
    {
        $termIds = [];

        try {
            if ($terms) {
                $aTerms = explode(',', $terms);

                foreach ($aTerms as $name) {

                    $name = trim($name);

                    if (!$name) {
                        continue;
                    }

                    $taxonomy = 'rrze_' . $type . '_tag';
                    $term = term_exists($name, $taxonomy);

                    if (!$term) {

                        $term = wp_insert_term($name, $taxonomy);

                        if (is_wp_error($term)) {
                            continue;
                        }

                        update_term_meta($term['term_id'], 'source', $identifier);
                    }

                    if (!is_array($term) && !is_wp_error($term)) {
                        $term = ['term_id' => (int) $term];
                    }

                    if (!is_wp_error($term) && isset($term['term_id'])) {
                        $termIds[] = (int) $term['term_id'];
                    }
                }
            }

            return $termIds;
        } catch (CustomException $e) {
            return new \WP_Error('setTags_error', __('Error in setTags().', 'rrze-answers'));
        }
    }

    public function getEntriesRemoteIDs($identifier, $type)
    {
        try {
            $aRet = [];
            $allEntries = get_posts(array('post_type' => 'rrze_' . $type, 'meta_key' => 'source', 'meta_value' => $identifier, 'fields' => 'ids', 'numberposts' => -1));// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            foreach ($allEntries as $postID) {
                $remoteID = get_post_meta($postID, 'remoteID', true);
                $remoteChanged = get_post_meta($postID, 'remoteChanged', true);
                if ($remoteID !== '') {
                    $aRet[$remoteID] = array(
                        'postID' => $postID,
                        'remoteChanged' => $remoteChanged,
                    );
                }
            }
            return $aRet;
        } catch (CustomException $e) {
            return new \WP_Error('getEntriesRemoteIDs_error', __('Error in getEntriesRemoteIDs().', 'rrze-answers'));
        }
    }

    public function setEntries($type, $identifier, $categories, $url)
    {

        try {
            $iNew = 0;
            $iUpdated = 0;
            $iDeleted = 0;
            $aURLhasSlider = [];
            $field_cpt = 'rrze_' . $type;

            $field_tag = 'rrze_' . $type . '_tag';
            $field_cat = 'rrze_' . $type . '_category';

            // get all remoteIDs of stored FAQ to this source ( key = remoteID, value = postID )
            $aRemoteIDs = $this->getEntriesRemoteIDs($identifier, $type);
            if (is_wp_error($aRemoteIDs)) {
                return $aRemoteIDs;
            }

            $aEntries = $this->getEntries($url, $categories, $type);
            if (is_wp_error($aEntries)) {
                return $aEntries;
            }

            // An empty response can be legitimate, but it is not safe to use one
            // request to remove an existing synchronized collection. Removing a
            // domain remains the explicit mechanism for deleting all of its data.
            if (empty($aEntries) && !empty($aRemoteIDs)) {
                return new \WP_Error(
                    'remote_empty_response',
                    __('The source site returned no entries. Existing synchronized entries were preserved.', 'rrze-answers')
                );
            }

            // set FAQ
            foreach ($aEntries as $entry) {
                if ($entry['URLhasSlider']) {
                    $aURLhasSlider[] = $entry['URLhasSlider'];
                    unset($aRemoteIDs[$entry['remoteID']]);
                    continue;
                }

                $tagIDs = $this->setTags($entry[$field_tag], $identifier, $type);
                if (is_wp_error($tagIDs)) {
                    return $tagIDs;
                }

                $categoryIDs = $this->setCategories($entry[$field_cat], $identifier, $type);
                if (is_wp_error($categoryIDs)) {
                    return $categoryIDs;
                }

                if (isset($aRemoteIDs[$entry['remoteID']])) {
                    if ($aRemoteIDs[$entry['remoteID']]['remoteChanged'] < $entry['remoteChanged']) {
                        // update FAQ
                        $post_id = wp_update_post(array(
                            'ID' => $aRemoteIDs[$entry['remoteID']]['postID'],
                            'post_name' => sanitize_title($entry['title']),
                            'post_title' => $entry['title'],
                            'post_content' => $entry['content'],
                            'meta_input' => array(
                                'source' => $identifier,
                                'lang' => $entry['lang'],
                                'remoteID' => $entry['remoteID'],
                                'remoteChanged' => $entry['remoteChanged'],
                            ),
                            'tax_input' => array(
                                $field_cat => $categoryIDs,
                                $field_tag => $tagIDs,
                            ),
                        ), true);

                        if (is_wp_error($post_id) || !$post_id) {
                            return is_wp_error($post_id)
                                ? $post_id
                                : new \WP_Error('sync_update_failed', __('A synchronized entry could not be updated. No entries were deleted.', 'rrze-answers'));
                        }

                        $iUpdated++;
                    }

                    unset($aRemoteIDs[$entry['remoteID']]);
                } else {
                    // insert FAQ
                    $post_id = wp_insert_post(array(
                        'post_type' => $field_cpt,
                        'post_name' => sanitize_title($entry['title']),
                        'post_title' => $entry['title'],
                        'post_content' => $entry['content'],
                        'comment_status' => 'closed',
                        'ping_status' => 'closed',
                        'post_status' => 'publish',
                        'meta_input' => array(
                            'source' => $identifier,
                            'lang' => $entry['lang'],
                            'remoteID' => $entry['remoteID'],
                            'remoteChanged' => $entry['remoteChanged'],
                            'sortfield' => '',
                        ),
                        'tax_input' => array(
                            $field_cat => $categoryIDs,
                            $field_tag => $tagIDs,
                        ),
                    ), true);

                    if (is_wp_error($post_id) || !$post_id) {
                        return is_wp_error($post_id)
                            ? $post_id
                            : new \WP_Error('sync_insert_failed', __('A synchronized entry could not be created. No entries were deleted.', 'rrze-answers'));
                    }

                    $iNew++;
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
        } catch (\Throwable $e) {
            return new \WP_Error('setFAQ_error', __('Error in setEntries().', 'rrze-answers'));
        }
    }

    /**
     * Normalize REST taxonomy values (term IDs or legacy names) to term names.
     *
     * @param mixed  $terms
     * @param string $taxonomy
     * @return string[]
     */
    private function restTermsToNames($terms, string $taxonomy): array
    {
        if (!is_array($terms)) {
            return [];
        }

        $names = [];

        foreach ($terms as $term) {
            if (is_numeric($term)) {
                $term_object = get_term((int) $term, $taxonomy);
                if ($term_object && !is_wp_error($term_object)) {
                    $names[] = $term_object->name;
                }
                continue;
            }

            if (is_string($term) && $term !== '') {
                $names[] = $term;
            }
        }

        return $names;
    }

    /**
     * Get remote content.
     *
     * @param string $url
     * @param array  $args
     * @param bool   $safe
     * @param bool   $use_cache
     * @return array|\WP_Error
     */
    private function remoteGet(string $url, array $args = [], bool $safe = true, bool $use_cache = true)
    {
        $cache_key = 'rrze_remote_' . md5($url);
        $cached = $use_cache ? get_transient($cache_key) : false;

        if (false !== $cached) {
            return $cached;
        }

        try {
            if (empty($args)) {
                $args = [
                    'sslverify' => defined('WP_DEBUG') && WP_DEBUG ? false : true,
                    'timeout' => 5,
                ];
            }

            if ($safe) {
                $ret = wp_safe_remote_get($url, $args);
            } else {
                $ret = wp_remote_get($url, $args);
            }

            // Only successful responses are safe to cache. Content sync bypasses
            // this cache so deletion decisions are never based on stale pages.
            if ($use_cache && !is_wp_error($ret) && (int) wp_remote_retrieve_response_code($ret) === 200) {
                set_transient($cache_key, $ret, 10 * MINUTE_IN_SECONDS);
            }

            return $ret;
        } catch (\Throwable $e) {
            return new \WP_Error('remoteGet_error', __('Error in remoteGet().', 'rrze-answers'));
        }
    }

    public function getDomains()
    {
        $domains = [];
        $options = get_option('rrze-answers');
        if (isset($options['registeredDomains'])) {
            foreach ($options['registeredDomains'] as $identifier => $url) {
                $domains[$identifier] = $url;
            }
        }
        asort($domains);
        return $domains;
    }

    public function checkDomain($identifier, $url, $domains)
    {
        // returns array('status' => TRUE, 'msg' => '')
        // on error returns array('status' => FALSE, 'ret' => error-message)
        $aRet = array('status' => FALSE, 'msg' => '');

        if (in_array($url, $domains)) {
            $aRet['msg'] = $url . ' ' . __('is already in use.', 'rrze-answers');
            return $aRet;
        } elseif (array_key_exists($identifier, $domains)) {
            $aRet['msg'] = $identifier . ' ' . __('is already in use.', 'rrze-answers');
            return $aRet;
        }

        $aSubEndpoints = ['faq', 'synonym', 'glossary'];

        foreach ($aSubEndpoints as $sub) {
            $request = wp_remote_get($url . '/' . ENDPOINT . $sub . '?per_page=1');
            $status_code = wp_remote_retrieve_response_code($request);

            if ($status_code != '200') {
                $aRet['msg'] = $url . ' ' . __('is not valid.', 'rrze-answers');
            } else {
                $content = json_decode(wp_remote_retrieve_body($request), TRUE);

                if (!$content) {
                    $aRet['ret'] = $url . ' ' . __(' does not support this plugin.', 'rrze-answers');
                } else {
                    $aRet['status'] = TRUE;
                    break;
                }
            }
        }

        return $aRet;
    }
}
