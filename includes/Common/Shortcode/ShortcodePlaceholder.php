<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Tools;

use function RRZE\Answers\plugin;



/**
 * Shortcode
 */
class ShortcodePlaceholder
{

    private $settings = '';
    private $pluginname = '';

    public function __construct()
    {
        $this->settings = $this->getShortcodeSettings();
        $this->pluginname = $this->settings['block']['blockname'];
        add_shortcode('placeholder', [$this, 'shortcodeOutput']);
        add_action('admin_head', [$this, 'setMCEConfig']);
        add_filter('mce_external_plugins', [$this, 'addMCEButtons']);
    }

    public function getShortcodeSettings(): array
    {
        return [
            'block' => [
                'blocktype' => 'rrze-answers/placeholder',
                'blockname' => 'placeholder',
                'title' => 'RRZE Placeholder',
                'category' => 'widgets',
                'icon' => 'translation',
                'tinymce_icon' => 'translate',
            ],
            'slug' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Slug', 'rrze-answers'),
                'type' => 'text'
            ],
            'id' => [
                'default' => 0,
                'field_type' => 'text',
                'label' => __('Placeholder', 'rrze-answers'),
                'type' => 'number'
            ],
            'lang' => [
                'default' => '',
                'field_type' => 'text',
                'label' => __('Language', 'rrze-answers'),
                'type' => 'text'
            ],
            'gutenberg_shortcode_type' => [
                'values' => [
                    'fau_abbr' => __('Abbreviation', 'rrze-answers'), // Abkürzung
                    'placeholder' => __('Longform', 'rrze-answers') // Ausgeschriebene Form
                ],
                'default' => 'placeholder',
                'field_type' => 'radio',
                'label' => __('Type of output', 'rrze-answers'),
                'type' => 'string'
            ],
            // 'additional_class' => [
            // 	'default' => '',
            // 	'field_type' => 'text',
            // 	'label' => __( 'Additonal CSS-class(es) for surrounding DIV', 'rrze-answers' ),
            // 	'type' => 'text'
            // ],
        ];

    }


    private function getPostBySlug($slug)
    {
        $ret = get_posts([
            'name' => $slug,
            'post_type' => 'rrze_placeholder',
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

    private function filterPostsByLanguage($posts, $lang)
    {
        if (!$lang || !is_array($posts)) {
            return $posts;
        }

        $lang = sanitize_text_field((string) $lang);

        return array_values(array_filter($posts, function ($post) use ($lang) {
            if (!($post instanceof \WP_Post)) {
                return false;
            }
            return ((string) get_post_meta($post->ID, 'lang', true)) === $lang;
        }));
    }

    public function shortcodeOutput($atts, $content = "", $shortcode_tag = "")
    {
        $myPosts = FALSE;

        // in case shortcode is used with slug although Gutenberg is enabled, we need to store the given value because slug has been unset() in fillGutenbergOptions() for usability reasons
        if (isset($atts['slug'])) {
            $slug = $atts['slug'];
        }

        // merge given attributes with default ones
        $atts_default = [];
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
            $myPosts = $this->getPostsByCPT('rrze_placeholder');
        }

        $myPosts = $this->filterPostsByLanguage($myPosts, $lang ?? '');

        $output = '';

        // echo '<pre>';
        // var_dump($myPosts[0]->post_content);
        // exit;

        if ($myPosts) {
                foreach ($myPosts as $post) {
                    $output .= '<div class="placeholder">';
                    $output .= html_entity_decode($post->post_content);
                    $output .= '<div>';
                }
            if (count($myPosts) > 1) {
                $output = '<div class="placeholder-outer">' . $output . '</div>';
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
            $pluginArray['rrze_shortcode'] = plugin()->getUrl() . 'assets/js/tinymce-shortcodes.js';
        }
        return $pluginArray;
    }
}


