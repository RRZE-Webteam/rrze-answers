<?php

namespace RRZE\Answers;

use RRZE\Answers\Defaults;

use RRZE\Answers\Common\{
    Settings\Settings,
    Settings\SettingsFAQ,
    CPT\CPTFAQ,
    Blocks\Blocks,
    Shortcode\Shortcode
};

defined('ABSPATH') || exit;

/**
 * Main class
 * 
 * This class serves as the entry point for the plugin.
 * It can be extended to include additional functionality or components as needed.
 * 
 * @package RRZE\Answers\Common
 * @since 1.0.0
 */
class Main
{
    /**
     * @var Defaults $defaults The defaults instance for the plugin.
     */
    public $defaults;

    /**
     * @var Settings $settings The settings instance for the plugin.
     */
    public $settings;

    /**
     * @var Blocks $blocks The blocks instance for the plugin.
     */
    public $blocks;

    /**
     * @var Shortcode $shortcode The shortcode instance for the plugin.
     * 
     * This property can be used to register custom shortcode.
     * It can be extended or modified to register additional shortcode as needed.
     */
    public $shortcode;

    /**
     * Constructor for the Main class.
     * 
     * This method initializes the plugin by loading (optionally) the settings.
     * It can also be used to initialize other components of the plugin.
     * 
     * @return void
     */
    public function __construct()
    {


        add_action('init', function () {
            $this->defaults = new Defaults();
            $this->cpt();
            $this->settings();
        });


        $this->shortcode();

        $this->blocks();

        // Initialize other components or functionality as needed.   
    }

    /**
     * Custom Post Type method
     * 
     * This method registers a custom post type using the CPT class.
     * It can be extended or modified to register additional custom post types as needed.
     * 
     * @return void
     */
    public function cpt()
    {
        // Example of registering a custom post type
        // This can be extended or modified as needed.
        $this->cpt = new CPTFAQ($this->defaults->get('cpt')['name'], [
            'labels' => [
                'name' => __('Books', 'rrze-answers'),
                'singular_name' => __('Book', 'rrze-answers')
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);
    }


    /**
     * Shortcode method
     * 
     * This method registers a shortcode using the Shortcode class.
     * It can be extended or modified to register additional shortcode as needed.
     * 
     * @return void
     */
    public function shortcode()
    {
        // Example of registering a shortcode.
        $this->shortcode = new Shortcode('example_shortcode', function ($atts, $content = null) {
            $atts = shortcode_atts([
                'title' => __('Default Title', 'rrze-answers'),
            ], $atts, 'example_shortcode');

            return '<div class="rrze-answers-example-shortcode">' . esc_html($atts['title']) . '</div>';
        });
    }

    /**
     * Blocks method
     * 
     * This method registers custom blocks using the Blocks class.
     * It can be extended or modified to register additional blocks as needed.
     * 
     * @return void
     */
    public function blocks()
    {
        $this->blocks = new Blocks(
            [                                  // Array of block names
                'block-static',
                'block-dynamic'
            ],
            plugin()->getPath('build/blocks'), // Blocks directory path
            plugin()->getPath()                // Plugin directory path
        );
    }

    /**
     * Settings method
     * 
     * This method sets up the plugin settings using the Settings class.
     * It defines the settings sections and options that will be available in the WordPress admin area
     * and provides validation and sanitization for the settings.
     * 
     * @return void
     */
    public function settings()
    {
        $this->settings = new Settings($this->defaults->get('settings')['page_title']);

        $this->settings->setCapability($this->defaults->get('settings')['capability'])
            ->setOptionName($this->defaults->get('settings')['option_name'])
            ->setMenuTitle($this->defaults->get('settings')['menu_title'])
            ->setMenuPosition(6)
            ->setMenuParentSlug('options-general.php');

        foreach ($this->defaults->get('sections') as $section) {
            $tab = $this->settings->addTab(__($section['title'], 'rrze-answers'), $section['id']);
            $sec = $tab->addSection(__($section['title'], 'rrze-answers'), $section['id']);

            foreach ($this->defaults->get('fields')[$section['id']] as $field) {
                $sec->addOption($field['type'], array_intersect_key(
                    $field,
                    array_flip(['name', 'label', 'description', 'options', 'default', 'sanitize', 'validate', 'placeholder'])
                ));
            }
        }

        $this->settings->build();
    }
}
