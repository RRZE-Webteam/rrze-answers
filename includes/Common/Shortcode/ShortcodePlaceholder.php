<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Content\EntryQuery;
use RRZE\Answers\Common\Content\PlaceholderRenderer;

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


    public function shortcodeOutput($atts, $content = "", $shortcode_tag = "")
    {
        $attributes = shortcode_atts(['slug' => '', 'id' => '', 'lang' => ''], $atts);
        $posts = EntryQuery::select('rrze_placeholder', $attributes);
        $output = '';

        foreach ($posts as $post) {
            $output .= '<div class="placeholder">' . PlaceholderRenderer::render($post) . '</div>';
        }

        return count($posts) > 1 ? '<div class="placeholder-outer">' . $output . '</div>' : $output;
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


