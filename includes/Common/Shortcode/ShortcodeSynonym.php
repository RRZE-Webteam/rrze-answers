<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Tools;


/**
 * Shortcode
 */
class ShortcodeSynonym
{

    private $settings = '';
    private $pluginname = '';

    public function __construct()
    {
        $this->settings = $this->getShortcodeSettings();
        $this->pluginname = $this->settings['block']['blockname'];
        add_shortcode('synonym', [$this, 'shortcodeOutput']); // liefert Langform (custom field) entweder nach slug oder id
        add_shortcode('fau_abbr', [$this, 'shortcodeOutput']); // liefert <abbr title=" synonym (custom field) " lang=" titleLang (custom field)" > title </abbr> nach slug oder id
        add_action('admin_head', [$this, 'setMCEConfig']);
        add_filter('mce_external_plugins', [$this, 'addMCEButtons']);
    }

    public function getShortcodeSettings(): array
    {
        return [
            'block' => [
                'blocktype' => 'rrze-synonym/synonym',
                'blockname' => 'synonym',
                'title' => 'RRZE Synonym',
                'category' => 'widgets',
                'icon' => 'translation',
                'tinymce_icon' => 'translate',
            ],
            'slug' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Slug', 'rrze-anwers'),
                'type' => 'text'
            ],
            'id' => [
                'default' => 0,
                'field_type' => 'text',
                'label' => __('Synonym', 'rrze-anwers'),
                'type' => 'number'
            ],
            'gutenberg_shortcode_type' => [
                'values' => [
                    'fau_abbr' => __('Abbreviation', 'rrze-anwers'), // Abkürzung
                    'synonym' => __('Longform', 'rrze-anwers') // Ausgeschriebene Form
                ],
                'default' => 'synonym',
                'field_type' => 'radio',
                'label' => __('Type of output', 'rrze-anwers'),
                'type' => 'string'
            ],
            // 'additional_class' => [
            // 	'default' => '',
            // 	'field_type' => 'text',
            // 	'label' => __( 'Additonal CSS-class(es) for surrounding DIV', 'rrze-anwers' ),
            // 	'type' => 'text'
            // ],
        ];

    }


    private function getPostBySlug($slug)
    {
        $ret = get_posts([
            'name' => $slug,
            'post_type' => 'synonym',
            'post_status' => 'publish',
            'posts_per_page' => 1
        ]);

        return (isset($ret[0]) ? $ret[0] : FALSE);
    }

    private function getPostsByCPT($cpt)
    {
        $postQuery = array('post_type' => $cpt, 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => false);
        return get_posts($postQuery);
    }

    public function shortcodeOutput($atts, $content = "", $shortcode_tag = "")
    {
        $myPosts = FALSE;

        // in case shortcode is used with slug although Gutenberg is enabled, we need to store the given value because slug has been unset() in fillGutenbergOptions() for usability reasons
        if (isset($atts['slug'])) {
            $slug = $atts['slug'];
        }

        // merge given attributes with default ones
        $atts_default = array();
        foreach ($this->settings as $k => $v) {
            if ($k != 'block') {
                $atts_default[$k] = $v['default'];
            }
        }
        $atts = shortcode_atts($atts_default, $atts);

        extract($atts);

        if (isset($slug) && $slug) {
            $myPosts = array($this->getPostBySlug($slug));
        } elseif ($id) {
            $myPosts = array(get_post($id));
        } else {
            // show all
            $myPosts = $this->getPostsByCPT('synonym');
        }

        if ($gutenberg_shortcode_type) {
            // Gutenberg
            $shortcode_tag = $gutenberg_shortcode_type;
        }

        $output = '';
        if ($myPosts) {
            switch ($shortcode_tag) {
                case 'fau_abbr':
                    if (count($myPosts) == 1) {
                        $post = $myPosts[0];
                        $output = '<abbr title="' . get_post_meta($post->ID, 'synonym', TRUE) . '" lang="' . get_post_meta($post->ID, 'titleLang', TRUE) . '">' . html_entity_decode($post->post_title) . '</abbr>';
                    } else {
                        foreach ($myPosts as $post) {
                            $output .= '<div class="fau_abbr">';
                            $output .= '<abbr title="' . get_post_meta($post->ID, 'synonym', TRUE) . '" lang="' . get_post_meta($post->ID, 'titleLang', TRUE) . '">' . html_entity_decode($post->post_title) . '</abbr>';
                            $output .= '</div>';
                        }
                    }
                    break;
                case 'synonym':
                    if (count($myPosts) == 1) {
                        $post = $myPosts[0];
                        $output = get_post_meta($post->ID, 'synonym', TRUE);
                    } else {
                        foreach ($myPosts as $post) {
                            $output .= '<div class="synonym">';
                            $output .= '<h2 class="small">' . html_entity_decode($post->post_title) . '</h2>';
                            $output .= '<p>' . get_post_meta($post->ID, 'synonym', TRUE) . '</p>';
                            $output .= '<div>';
                        }
                    }
                    break;
            }
            if (count($myPosts) > 1) {
                $output = '<div class="synonym-outer">' . $output . '</div>';
            }
        }
        return $output;
    }


    public function setMCEConfig()
    {
        $shortcode = '';
        foreach ($this->settings as $att => $details) {
            if ($att != 'block' && $att != 'gutenberg_shortcode_type') {
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
            $pluginArray['rrze_shortcode'] = plugins_url('../assets/js/tinymce-shortcodes.js', plugin_basename(__FILE__));
        }
        return $pluginArray;
    }
}


