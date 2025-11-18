<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

use function RRZE\Answers\plugin;


use RRZE\Answers\Common\Tools;
/**
 * Shortcode
 */
class ShortcodeGlossary
{



    private $settings = '';
    private $pluginname = '';

    public function __construct()
    {
        $this->settings = $this->getShortcodeSettings();
        $this->pluginname = $this->settings['block']['blockname'];
        add_shortcode('glossary', [$this, 'shortcodeOutput']);
        add_shortcode('fau_glossar', [$this, 'shortcodeOutput']);
        add_action('admin_head', [$this, 'setMCEConfig']);
        add_filter('mce_external_plugins', [$this, 'addMCEButtons']);
    }

    function getShortcodeSettings()
    {
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
                        'val' => __('none', 'rrze-answers')
                    ],
                    [
                        'id' => 'category',
                        'val' => __('Categories', 'rrze-answers')
                    ],
                    [
                        'id' => 'tag',
                        'val' => __('Tags', 'rrze-answers')
                    ]
                ],
                'default' => '',
                'field_type' => 'select',
                'label' => __('Register content', 'rrze-answers'),
                'type' => 'string'
            ],
            'registerstyle' => [
                'values' => [
                    [
                        'id' => '',
                        'val' => __('-- hidden --', 'rrze-answers')
                    ],
                    [
                        'id' => 'a-z',
                        'val' => __('A - Z', 'rrze-answers')
                    ],
                    [
                        'id' => 'tagcloud',
                        'val' => __('Tagcloud', 'rrze-answers')
                    ],
                    [
                        'id' => 'tabs',
                        'val' => __('Tabs', 'rrze-answers')
                    ]
                ],
                'default' => 'a-z',
                'field_type' => 'select',
                'label' => __('Register style', 'rrze-answers'),
                'type' => 'string'
            ],
            'category' => [
                'default' => '0',
                'field_type' => 'text',
                'label' => __('Categories', 'rrze-answers'),
                'type' => 'string'
            ],
            'tag' => [
                'default' => 0,
                'field_type' => 'text',
                'label' => __('Tags', 'rrze-answers'),
                'type' => 'string'
            ],
            'id' => [
                'default' => NULL,
                'field_type' => 'text',
                'label' => __('Glossary', 'rrze-answers'),
                'type' => 'number'
            ],
            'masonry' => [
                'field_type' => 'toggle',
                'label' => __('Grid', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'hide_accordion' => [
                'field_type' => 'toggle',
                'label' => __('Hide accordion', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'hide_title' => [
                'field_type' => 'toggle',
                'label' => __('Hide title', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'expand_all_link' => [
                'field_type' => 'toggle',
                'label' => __('Show "expand all" button', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
            ],
            'load_open' => [
                'field_type' => 'toggle',
                'label' => __('Load website with opened accordions', 'rrze-answers'),
                'type' => 'boolean',
                'default' => FALSE,
                'checked' => FALSE
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
                'label' => __('Color', 'rrze-answers'),
                'type' => 'string'
            ],
            'style' => [
                'values' => [
                    [
                        'id' => '',
                        'val' => __('none', 'rrze-answers')
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
                'label' => __('Style', 'rrze-answers'),
                'type' => 'string'
            ],
            'additional_class' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Additonal CSS-class(es) for sourrounding DIV', 'rrze-answers'),
                'type' => 'string'
            ],
            'lang' => [
                'default' => '',
                'field_type' => 'select',
                'label' => __('Language', 'rrze-answers'),
                'type' => 'string'
            ],
            'sort' => [
                'values' => [
                    [
                        'id' => 'title',
                        'val' => __('Title', 'rrze-answers')
                    ],
                    [
                        'id' => 'id',
                        'val' => __('ID', 'rrze-answers')
                    ],
                    [
                        'id' => 'sortfield',
                        'val' => __('Sort field', 'rrze-answers')
                    ],
                ],
                'default' => 'title',
                'field_type' => 'select',
                'label' => __('Sort', 'rrze-answers'),
                'type' => 'string'
            ],
            'order' => [
                'values' => [
                    [
                        'id' => 'ASC',
                        'val' => __('ASC', 'rrze-answers')
                    ],
                    [
                        'id' => 'DESC',
                        'val' => __('DESC', 'rrze-answers')
                    ],
                ],
                'default' => 'ASC',
                'field_type' => 'select',
                'label' => __('Order', 'rrze-answers'),
                'type' => 'string'
            ],
            'hstart' => [
                'default' => 2,
                'field_type' => 'text',
                'label' => __('Heading level of the first heading', 'rrze-answers'),
                'type' => 'number'
            ],
        ];
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
     * @param bool $masonry Whether tiles should be displayed (fake masonry - see https://github.com/RRZE-Webteam/rrze-answers/issues/105#issuecomment-2873361435 )
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

                if ($useSchema) {
                    $this->bSchema = true;
                }

                if ($hide_accordion) {
                    $content .= Tools::renderFAQItem($question, $answer, $hstart, $useSchema, $hide_title);
                } else {
                    $content .= Tools::renderFAQItemAccordion($anchorfield, $question, $answer, $color, $load_open, $useSchema);
                }
            }
        }

        return $content;
    }

    /**
     * Outputs FAQs based on taxonomies (category/tag) or glossary view.
     * 
     * Supports classic and alphabetical output, tabs or tag cloud display.
     * 
     * @param array $atts Original shortcode attributes
     * @param int $hstart HTML heading level
     * @param string $style Inline styles for the accordion
     * @param string $expand_all_link Attribute for “expand all” link
     * @param bool $hide_accordion Whether the accordion should be suppressed
     * @param bool $hide_title Whether the title should be suppressed
     * @param string $color Color attribute
     * @param string $load_open Attribute for open state
     * @param string $sort Sort criterion (title, id, sortfield)
     * @param string $order Sort order
     * @param mixed $category Category(ies) as string or array
     * @param mixed $tag Tag(s) as string or array
     * @param string $register “category” or “tag”
     * @param string $registerstyle “a-z”, “tabs”, “tagcloud” or empty
     * @return string Rendered HTML content
     */
    private function renderFilteredFAQs(array $atts, int $hstart, string $style, string $expand_all_link, bool $hide_accordion, bool $hide_title, string $color, string $load_open, string $sort, string $order, $category, $tag, string $register, string $registerstyle): string
    {
        $content = '';
        $this->bSchema = false;

        // attribute category or tag is given or none of them
        $aLetters = [];
        $tax_query = '';

        $postQuery = array('post_type' => 'rrze_glossary', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => false);
        if ($sort == 'sortfield') {
            $postQuery['orderby'] = array(
                'meta_value' => $order, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'title' => $order,
            );
            $postQuery['meta_key'] = 'sortfield'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        } else {
            $postQuery['orderby'] = $sort;
            $postQuery['order'] = $order;
        }

        // filter by category and/or tag and -if given- by domain related to category/tag, too
        $aTax = [];
        $aTax['rrze_glossary_category'] = Tools::getTaxBySource($category);
        $aTax['rrze_glossary_tag'] = Tools::getTaxBySource($tag);
        $aTax = array_filter($aTax); // delete empty entries

        if ($aTax) {
            $tax_query = Tools::getTaxQuery($aTax);
            if ($tax_query) {
                $postQuery['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            }
        }

        $metaQuery = [];
        $lang = $atts['lang'] ? trim($atts['lang']) : '';

        if ($lang) {
            $metaQuery[] = [
                'key' => 'lang',
                'value' => $lang,
                'compare' => '=',
            ];
        }

        $source = !empty($atts['domain']) ?
            array_filter(array_map('trim', explode(',', $atts['domain']))) :
            [];
        if ($source) {
            $metaQuery[] = [
                'key' => 'source',
                'value' => $source,
                'compare' => 'IN',
            ];
        }

        if ($metaQuery) {
            $postQuery['meta_query'] = array_merge([ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'relation' => 'AND'
            ], $metaQuery);
        }

        $posts = get_posts($postQuery);

        if ($posts) {
            if ($register) {
                // attribut register is given
                // get all used tags or categories
                $aUsedTerms = [];
                $aPostIDs = [];
                foreach ($posts as $post) {
                    // get all tags for each post
                    $aTermIds = [];
                    $valid_term_ids = [];
                    if ($register == 'category' && $category) {
                        if (!is_array($category)) {
                            $aCats = array_map('trim', explode(',', $category));
                        } else {
                            $aCats = $category;
                        }
                        foreach ($aCats as $slug) {
                            $filter_term = get_term_by('slug', $slug, 'rrze_faq_category');
                            if ($filter_term) {
                                $valid_term_ids[] = $filter_term->term_id;
                            }
                        }
                    } elseif ($register == 'tag' && $tag) {
                        if (!is_array($tag)) {
                            $aTags = array_map('trim', explode(',', $tag));
                        } else {
                            $aTags = $tag;
                        }
                        foreach ($aTags as $slug) {
                            $filter_term = get_term_by('slug', $slug, 'rrze_faq_tag');
                            if ($filter_term) {
                                $valid_term_ids[] = $filter_term->term_id;
                            }
                        }
                    }
                    $terms = wp_get_post_terms($post->ID, 'rrze_glossary_' . $register);
                    if ($terms) {
                        foreach ($terms as $t) {
                            if ($valid_term_ids && in_array($t->term_id, $valid_term_ids) === false) {
                                continue;
                            }
                            $aTermIds[] = $t->term_id;
                            $letter = Tools::getLetter($t->name);
                            $aLetters[$letter] = true;
                            $aUsedTerms[$t->name] = array('letter' => $letter, 'ID' => $t->term_id);
                            $aPostIDs[$t->term_id][] = $post->ID;
                        }
                    }
                }
                ksort($aUsedTerms);
                $anchor = 'ID';
                if ($aLetters) {
                    switch ($registerstyle) {
                        case 'a-z':
                            $content = Tools::createAZ($aLetters);
                            $anchor = 'letter';
                            break;
                        case 'tabs':
                            $content = Tools::createTabs($aUsedTerms, $aPostIDs);
                            break;
                        case 'tagcloud':
                            $content = Tools::createTagCloud($aUsedTerms, $aPostIDs);
                            break;
                    }
                }

                $last_anchor = '';
                foreach ($aUsedTerms as $k => $aVal) {
                    if ($registerstyle == 'a-z' && $content) {
                        $content .= ($last_anchor != $aVal[$anchor] ? '<h2 id="' . $anchor . '-' . $aVal[$anchor] . '">' . esc_html($aVal[$anchor]) . '</h2>' : '');
                    }

                    $term_id_attr = $anchor . '-' . $aVal[$anchor];
                    $content .= '<section id="' . esc_attr($term_id_attr) . '" class="faq-item is-' . $color . '">';
                    $content .= '<h3>' . esc_html($k) . '</h3>';

                    $content .= '<div class="faq-term-content">';

                    // find the postIDs to this tag
                    $aIDs = Tools::searchArrayByKey($aVal['ID'], $aPostIDs);

                    foreach ($aIDs as $ID) {
                        $source = get_post_meta($ID, "source", true);
                        $useSchema = ($source === 'website');

                        if ($useSchema) {
                            $this->bSchema = true;
                        }

                        $question = get_the_title($ID);
                        $answer = str_replace(']]>', ']]&gt;', apply_filters('the_content', get_post_field('post_content', $ID)));
                        $anchorfield = get_post_meta($ID, 'anchorfield', true);

                        if (empty($anchorfield)) {
                            $anchorfield = 'innerID-' . $ID;
                        }

                        $content .= Tools::renderFAQItemAccordion($anchorfield, $question, $answer, $color, $load_open, $useSchema);
                    }

                    $content .= '</div></section>';
                    $last_anchor = $aVal[$anchor];
                }
            } else {
                // attribut register is not given
                $last_anchor = '';
                foreach ($posts as $post) {
                    $source = get_post_meta($post->ID, "source", true);
                    $useSchema = ($source === 'website');

                    if ($useSchema) {
                        $this->bSchema = true;
                    }

                    $question = get_the_title($post->ID);
                    $answer = str_replace(']]>', ']]&gt;', apply_filters('the_content', get_post_field('post_content', $post->ID)));

                    $letter = Tools::getLetter($question);
                    $aLetters[$letter] = true;

                    if (!$hide_accordion) {
                        $anchorfield = get_post_meta($post->ID, 'anchorfield', true);

                        if (empty($anchorfield)) {
                            $anchorfield = 'ID-' . $post->ID;
                        }

                        if ($registerstyle == 'a-z' && count($posts) > 1) {
                            $content .= ($last_anchor != $letter ? '<h2 id="letter-' . $letter . '">' . $letter . '</h2>' : '');
                        }

                        $content .= Tools::renderFAQItemAccordion($anchorfield, $question, $answer, $color, $load_open, $useSchema);
                    } else {
                        $content .= Tools::renderFAQItem($question, $answer, $hstart, $useSchema, $hide_title);
                    }
                    $last_anchor = $letter;
                }

            }
        }

        return $content;
    }

    private function getLetter(&$txt)
    {
        return mb_strtoupper(mb_substr(remove_accents($txt), 0, 1), 'UTF-8');
    }

    private function createAZ(&$aSearch)
    {
        if (count($aSearch) == 1) {
            return '';
        }
        $ret = '<div class="fau-glossar"><ul class="letters">';
        foreach (range('A', 'Z') as $a) {
            if (array_key_exists($a, $aSearch)) {
                $ret .= '<li class="filled"><a href="#letter-' . $a . '">' . $a . '</a></li>';
            } else {
                $ret .= '<li aria-hidden="true" role="presentation"><span>' . $a . '</span></li>';
            }
        }
        return $ret . '</ul></div>';
    }

    private function createTabs(&$aTerms, $aPostIDs)
    {
        if (count($aTerms) == 1) {
            return '';
        }
        $ret = '<div class="fau-glossar"><ul class="letters">';
        foreach ($aTerms as $name => $aDetails) {
            $ret .= '<a href="#ID-' . $aDetails['ID'] . '">' . $name . '</a> | ';
        }
        return rtrim($ret, ' | ') . '</div>';
    }

    private function createTagcloud(&$aTerms, $aPostIDs)
    {
        if (count($aTerms) == 1) {
            return '';
        }
        $ret = '<div class="fau-glossar"><ul class="letters">';
        $smallest = 12;
        $largest = 22;
        $aCounts = [];
        foreach ($aTerms as $name => $aDetails) {
            $aCounts[$aDetails['ID']] = count($aPostIDs[$aDetails['ID']]);
        }
        $iMax = max($aCounts);
        $aSizes = [];
        foreach ($aCounts as $ID => $cnt) {
            $aSizes[$ID] = round(($cnt / $iMax) * $largest, 0);
            $aSizes[$ID] = ($aSizes[$ID] < $smallest ? $smallest : $aSizes[$ID]);
        }
        foreach ($aTerms as $name => $aDetails) {
            $ret .= '<a href="#ID-' . $aDetails['ID'] . '" style="font-size:' . $aSizes[$aDetails['ID']] . 'px">' . $name . '</a> | ';
        }
        return rtrim($ret, ' | ') . '</div>';
    }

    private function getTaxQuery(&$aTax)
    {
        $ret = '';
        $aTmp = [];
        foreach ($aTax as $field => $aVal) {
            if ($aVal[0]) {
                $aTmp[] = array(
                    'taxonomy' => 'glossary_' . $field,
                    'field' => 'slug',
                    'terms' => $aVal
                );
            }
        }
        if ($aTmp) {
            $ret = $aTmp;
            if (count($aTmp) > 1) {
                $ret['relation'] = 'AND';
            }
        }
        return $ret;
    }

    private function searchArrayByKey(&$needle, &$aHaystack)
    {
        foreach ($aHaystack as $k => $v) {
            if ($k === $needle) {
                return $v;
            }
        }
        return FALSE;
    }


    /**
     * Translates composite shortcode attributes into individual properties
     * 
     * Splits the values of attributes such as “register”, “hide”, “show” and “class” into sub-terms 
     * and assigns these to logical individual fields in the attribute array. This simplifies further internal processing.
     * 
     * @param array $atts Reference to the shortcode attribute array
     * @return void
     */
    private function translateNewAttributes(array &$atts): void
    {
        // translate new attributes
        if (isset($atts['register'])) {
            $parts = explode(' ', $atts['register']);
            foreach ($parts as $part) {
                $part = trim($part);
                switch ($part) {
                    case 'category':
                    case 'tag':
                        $atts['register'] = $part;
                        break;
                    case 'a-z':
                    case 'tabs':
                    case 'tagcloud':
                        $atts['registerstyle'] = $part;
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
                    case 'register':
                        $atts['registerstyle'] = '';
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
     * Generate the shortcode output
     * @param array $atts Shortcode attributes
     * @param string $content Enclosed content
     * @return string Return the content
     */
    public function shortcodeOutput($atts, $content = null, $shortcode_tag = '')
    {
        // Workaround - see: https://github.com/RRZE-Webteam/rrze-answers/issues/132#issuecomment-2839668060
        if (($skip = Tools::preventGutenbergDoubleBracketBug($shortcode_tag)) !== false) {
            return $skip;
        }

        if (empty($atts)) {
            $atts = [];
        } else {
            $atts = array_map('sanitize_text_field', $atts);
        }

        $this->translateNewAttributes($atts);

        // merge given attributes with default ones
        $atts_default = [];
        foreach ($this->settings as $k => $v) {
            if ($k != 'block') {
                $atts_default[$k] = $v['default'];
            }
        }

        $atts = shortcode_atts($atts_default, $atts);
        extract($atts);

        $content = '';
        $register = (string) ($register ?? '');
        $registerstyle = (string) ($registerstyle ?? '');
        $hide_title = (isset($hide_title) ? $hide_title : false);
        $color = (isset($color) ? $color : '');
        $style = (isset($style) ? 'style="' . $style . '"' : '');

        $gutenberg = (is_array($id) ? true : false);

        if ($id && (!$gutenberg || $gutenberg && $id[0])) {
            $content = $this->renderExplicitFAQs($id, $gutenberg, $hstart, $style, $masonry, $expand_all_link, $hide_accordion, $hide_title, $color, $load_open);
        } else {
            $content = $this->renderFilteredFAQs($atts, $hstart, $style, $expand_all_link, $hide_accordion, $hide_title, $color, $load_open, $sort, $order, $category, $tag, $register, $registerstyle);
        }

        // 2020-05-12 THIS IS NOT IN USE because f.e. [faq glossary="category"] led to errors ("TypeError: e.$slides is null slick.min.js" and "TypeError: can't access property "add"" ) as FAQ can have >1 category and so equal sliders would be returned in output which leads to JS errors that avoid accordeons to work properly
        // => sliders are not syncable / this info is provided to the user during Sync and in Logfile
        // check if theme 'FAU-Einrichtungen' and [gallery ...] is in use
        // if ( ( wp_get_theme()->Name == 'FAU-Einrichtungen' ) && ( strpos( $content, 'slider') !== false ) ) {
        //     wp_enqueue_script( 'fau-js-heroslider' );
        // }

        $postID = get_the_ID();
        $headerID = (new Tools())->getHeaderID($postID);

        wp_enqueue_script('rrze-answers-accordion');
        wp_enqueue_style('rrze-answers-css');

        $content = Tools::renderFAQWrapper($postID, $content, $headerID, $masonry, $color, $additional_class, $this->bSchema);

        return $content;

    }

    public function sortIt(&$arr)
    {
        uasort($arr, function ($a, $b) {
            return strtolower($a) <=> strtolower($b);
        });
    }

    public function setMCEConfig()
    {
        $shortcode = '';
        foreach ($this->settings as $att => $details) {
            if ($att != 'block') {
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

    public function addMCEButtons($pluginArray)
    {
        if (current_user_can('edit_posts') && current_user_can('edit_pages')) {
            $pluginArray['rrze_shortcode'] = plugin()->getUrl() . 'assets/js/tinymce-shortcodes.js';
        }
        return $pluginArray;
    }
}




