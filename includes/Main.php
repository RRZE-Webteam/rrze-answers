<?php

namespace RRZE\Answers;

use function RRZE\Answers\plugin;

use RRZE\Answers\Defaults;

use RRZE\Answers\Common\{
    API\RESTAPI,
    AdminInterfaces\AdminUI_QA,
    AdminInterfaces\AdminUI_Synonym,
    // AdminInterfaces\AdminMenu,
    // AdminInterfaces\AdminInterfaces,
    // AdminInterfaces\AdminInterfacesSynonym,
    Settings\Settings,
    // Settings\SettingsFAQ,
    CPT\CPTFAQ,
    CPT\CPTGlossary,
    CPT\CPTSynonym,
    Blocks\Blocks,
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
    public $defaults;
    public $restapi;
    public $settings;
    // public $settingsFAQ;

    // public $blocks;
    public $shortcodeFAQ;
    private $adminMenu;
    // private $adminInterface;
    private $adminUI;

    public function __construct()
    {
        $this->cpt();
        add_action('init', [$this, 'onInit']);
        add_filter('wp_kses_allowed_html', [$this, 'my_custom_allowed_html'], 10, 2);
    }

    public function onInit()
    {
        $this->defaults = new Defaults();
        $this->settings();
        $this->restapi = new RESTAPI();

        // $this->adminInterface = new AdminInterfaces('rrze_faq');
        // $this->adminInterface = new AdminInterfaces('rrze_glossary');
        // $this->adminInterface = new AdminInterfacesSynonym();
        $this->adminUI = new AdminUI_QA('rrze_faq');
        $this->adminUI = new AdminUI_QA('rrze_glossary');
        $this->adminUI = new AdminUI_Synonym();

        // $this->adminMenue = new AdminMenu(); // in admin menu there is a maximum of 2 levels. Deactivated this workaround because it wouldn't be best practice.
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('enqueue_block_assets', [$this, 'enqueueScripts']);

        $this->shortcode();
        $this->blocks();
    }

    /**
     * Allow needed HTML on post content sanitized by wp_kses_post().
     *
     * @param array  $allowed_tags The current allowed tags/attributes for the given context.
     * @param string $context      KSES context; wp_kses_post() uses 'post'.
     * @return array               Modified allowed tags/attributes.
     */
    function my_custom_allowed_html($allowed_tags, $context)
    {
        // Only alter the 'post' context used by wp_kses_post()
        if ($context !== 'post') {
            return $allowed_tags;
        }

        // 1) Schema.org microdata attributes we want to allow on various elements
        $schema_attrs = [
            'itemscope' => true, // boolean attribute (no value needed)
            'itemtype' => true, // URL to schema type, e.g. https://schema.org/FAQPage
            'itemprop' => true, // property name within the item
            'itemid' => true, // global identifier
            'itemref' => true, // references other elements by ID
        ];

        // 2) HTML5 elements that may carry microdata in your templates/shortcodes
        $tags_to_extend = [
            'div',
            'span',
            'p',
            'a',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'ul',
            'ol',
            'li',
            'section',
            'article',
            'header',
            'footer',
            'main',
            'nav',
            'details',
            'summary'
        ];

        // Ensure details/summary exist with common attributes for accordion UI
        if (!isset($allowed_tags['details'])) {
            $allowed_tags['details'] = [];
        }
        $allowed_tags['details'] = array_merge($allowed_tags['details'], [
            'id' => true,
            'class' => true,
            'open' => true, // render expanded by default
        ]);

        if (!isset($allowed_tags['summary'])) {
            $allowed_tags['summary'] = [];
        }
        $allowed_tags['summary'] = array_merge($allowed_tags['summary'], [
            'id' => true,
            'class' => true,
        ]);

        // 3) Add Schema.org attributes to the listed tags without removing existing ones
        foreach ($tags_to_extend as $tag) {
            if (!isset($allowed_tags[$tag])) {
                $allowed_tags[$tag] = [];
            }
            $allowed_tags[$tag] = array_merge($allowed_tags[$tag], $schema_attrs);
        }

        // 4) (Optional) keep your form elements if you output any in content
        $allowed_tags['select'] = array_merge($allowed_tags['select'] ?? [], [
            'name' => true,
            'id' => true,
            'class' => true,
            'multiple' => true,
            'size' => true,
        ]);

        $allowed_tags['option'] = array_merge($allowed_tags['option'] ?? [], [
            'value' => true,
            'selected' => true,
        ]);

        $allowed_tags['input'] = array_merge($allowed_tags['input'] ?? [], [
            'type' => true,
            'name' => true,
            'id' => true,
            'class' => true,
            'value' => true,
            'placeholder' => true,
            'checked' => true,
            'disabled' => true,
            'readonly' => true,
            'maxlength' => true,
            'size' => true,
            'min' => true,
            'max' => true,
            'step' => true,
        ]);

        return $allowed_tags;
    }

    // public function settingsAll()
    // {
    //     $this->settingsFAQ = new SettingsFAQ(plugin()->getFile());
    // }

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
    public function blocks()
    {

        $this->blocks = new Blocks(
            [                                  // Array of block names
                'faq',
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


