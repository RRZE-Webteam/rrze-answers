<?php

namespace RRZE\Answers;

use function RRZE\Answers\plugin;

use RRZE\Answers\Defaults;

use RRZE\Answers\Common\{
    AdminInterfaces\AdminMenu,
    AdminInterfaces\AdminInterfacesFAQ,
    Settings\Settings,
    Settings\SettingsFAQ,
    CPT\CPTFAQ,
    CPT\CPTGlossary,
    CPT\CPTSynonym,
    // Blocks\Blocks,
    Shortcode\ShortcodeFAQ,
    Shortcode\ShortcodeGlossary,
    Shortcode\ShortcodeSynonym
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
    private $textdomain = 'rrze-answers';
    public $defaults;
    public $settings;
    public $settingsFAQ;

    // public $blocks;
    public $shortcodeFAQ;
    private $adminMenu;
    private $adminInterface;

    public function __construct()
    {
        $this->cpt();
        add_action('init', [$this, 'onInit']);
    }

    public function onInit()
    {
        $this->defaults = new Defaults();
        $this->settings();
        $this->settingsAll();
        $this->adminInterface = new AdminInterfacesFAQ();
        // $this->adminMenue = new AdminMenu(); // in admin menu there is a maximum of 2 levels. Deactivated this workaround because it wouldn't be best practice.
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('enqueue_block_assets', [$this, 'enqueueScripts']);

        $this->shortcode();
        // $this->blocks();
    }

    public function settingsAll()
    {
        $this->settingsFAQ = new SettingsFAQ(plugin()->getFile());
    }

    public function cpt()
    {
        $this->cpt = new CPTFAQ();
        $this->cpt = new CPTGlossary();
        $this->cpt = new CPTSynonym();
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
        $this->shortcode = new ShortcodeFAQ();
        $this->shortcode = new ShortcodeGlossary();
        $this->shortcode = new ShortcodeSynonym();
    }

    /**
     * Blocks method
     * 
     * This method registers custom blocks using the Blocks class.
     * It can be extended or modified to register additional blocks as needed.
     * 
     * @return void
     */
    // public function blocks()
    // {

    //     $this->blocks = new Blocks(
    //         [                                  // Array of block names
    //             'faq',
    //         ],
    //         plugin()->getPath('build/blocks'), // Blocks directory path
    //         plugin()->getPath()                // Plugin directory path
    //     );
    // }

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

    /**
     * Enqueue der globale Skripte.
     */
    public function enqueueScripts()
    {
        wp_register_style(
            'rrze-faq-css',
            plugins_url('build/css/rrze-faq.css', plugin()->getBasename()),
            [],
            filemtime(plugin()->getPath() . 'build/css/rrze-faq.css')
        );

        wp_register_style(
            'rrze-synonym-css',
            plugins_url('build/css/rrze-synonym.css', plugin()->getBasename()),
            [],
            filemtime(plugin()->getPath() . 'build/css/rrze-synonym.css')
        );

        wp_register_script(
            'rrze-faq-accordion',
            plugins_url('build/rrze-faq-accordion.js', plugin()->getBasename()),
            array('jquery'),
            filemtime(plugin()->getPath() . 'build/rrze-faq-accordion.js'),
            true
        );
    }

}


