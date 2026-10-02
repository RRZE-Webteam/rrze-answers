<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Content\EntryQuery;

use function RRZE\Answers\plugin;



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
                'label' => __('Slug', 'rrze-answers'),
                'type' => 'text'
            ],
            'id' => [
                'default' => 0,
                'field_type' => 'text',
                'label' => __('Synonym', 'rrze-answers'),
                'type' => 'number'
            ],
            'gutenberg_shortcode_type' => [
                'values' => [
                    'fau_abbr' => __('Abbreviation', 'rrze-answers'), // Abkürzung
                    'synonym' => __('Longform', 'rrze-answers') // Ausgeschriebene Form
                ],
                'default' => 'synonym',
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


    public function shortcodeOutput($atts, $content = "", $shortcode_tag = "")
    {
        $attributes = shortcode_atts(['slug' => '', 'id' => '', 'lang' => ''], $atts);
        $posts = EntryQuery::select('rrze_synonym', $attributes);
        $abbreviation = $shortcode_tag === 'fau_abbr';
        $multiple = count($posts) > 1;
        $output = '';

        if ($abbreviation && $posts !== []) {
            wp_enqueue_style('rrze-answers-css');
        }

        foreach ($posts as $post) {
            $title = esc_html(html_entity_decode($post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $longForm = (string) get_post_meta($post->ID, 'synonym', true);

            if ($abbreviation) {
                $item = '<abbr class="rrze-syn" title="' . esc_attr($longForm)
                    . '" lang="' . esc_attr((string) get_post_meta($post->ID, 'titleLang', true))
                    . '">' . $title . '</abbr>';
                $output .= $multiple ? '<div class="fau_abbr">' . $item . '</div>' : $item;
            } else {
                $item = esc_html($longForm);
                $output .= $multiple
                    ? '<div class="synonym"><h2 class="small">' . $title . '</h2><p>' . $item . '</p></div>'
                    : $item;
            }
        }

        return $multiple ? '<div class="synonym-outer">' . $output . '</div>' : $output;
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


