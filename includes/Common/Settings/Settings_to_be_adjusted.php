<?php

namespace RRZE\FAQ;

defined('ABSPATH') || exit;

use RRZE\Answers\Config;
use RRZE\Answers\API;



/**
 * Settings class
 */
class Settings
{
     private $cpt = [];
   /**
     * The complete path and file name of the plugin file.
     * @var string
     */
    protected $pluginFile;

    /**
     * Option name
     * @var string
     */
    protected $optionName;

    /**
     * Settings options
     * @var array
     */
    protected $options;

    /**
     * Settings menue
     * @var array
     */
    protected $settingsMenu;

    /**
     * Settings areas
     * @var array
     */
    protected $settingsSections;

    /**
     * Settings fields
     * @var array
     */
    protected $settingsFields;

    /**
     * All tabs
     * @var array
     */
    protected $allTabs = [];

    /**
     * Standard tab
     * @var string
     */
    protected $defaultTab = '';

    /**
     * Current tab
     * @var string
     */
    protected $currentTab = '';


    /**
     * Registered domains
     * @var string
     */
    protected $domains = array();

    /**
     * Options page
     * @var string
     */
    protected $optionsPage;

    /**
     * Assign values to variables.
     * @param string $pluginFile [description]
     * 
     */

    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
         $this->cpt = Config::getConstants('cpt');
   }

    /**
     * It is executed as soon as the class is instantiated.
     * @return void
     */
    public function onLoaded()
    {
        add_action('init', [$this, 'regularInit'], 1);
        add_action('admin_init', [$this, 'adminInit']);

        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        add_action('init', [$this, 'maybeFlushRewriteRules'], 20);
        add_action('update_option_rrze-faq', [$this, 'checkSlugChange'], 10, 2);

        add_action('template_redirect', [$this, 'maybe_disable_canonical_redirect'], 1);
        add_action('template_redirect', [$this, 'custom_cpt_404_message']);

    }
    public function checkSlugChange($old_value, $value)
    {
        $rewriteKeys = [
            'website_custom_faq_slug',
            'website_custom_faq_category_slug',
            'website_custom_faq_tag_slug',
        ];

        foreach ($rewriteKeys as $key) {
            if (isset($old_value[$key], $value[$key]) && $old_value[$key] !== $value[$key]) {
                set_transient('rrze_faq_flush_rewrite_needed', true, 60); // 1 minute is enough 
                break;
            }
        }
    }

    public function maybeFlushRewriteRules()
    {
        if (get_transient('rrze_faq_flush_rewrite_needed')) {
            flush_rewrite_rules();
            delete_transient('rrze_faq_flush_rewrite_needed');
        }
    }

    public function rrze_faq_get_redirect_page_url($options): string
    {
        $redirect_id = isset($this->options['website_redirect_archivpage_uri']) ? (int) $this->options['website_redirect_archivpage_uri'] : 0;
        if ($redirect_id > 0) {
            $post = get_post($redirect_id);
            if ($post && get_post_status($post) === 'publish') {
                return get_permalink($redirect_id);
            }
        }
        return '';
    }

    public static function is_slug_request($slug): bool
    {
        if (empty($slug)) {
            return false;
        }

        global $wp;
        $request_path = trim($wp->request, '/');

        return $request_path === trim($slug, '/');
    }


    public function rrze_faq_redirect_if_needed(string $custom_slug): void
    {
        if (!self::is_slug_request($custom_slug)) {
            return;
        }

        $target_url = rrze_faq_get_redirect_page_url($this->options);
        if (!empty($target_url)) {
            wp_redirect(esc_url_raw($target_url), 301);
            exit;
        }
    }

    public function rrze_faq_disable_canonical_redirect_if_needed(string $custom_slug): void
    {
        if (!self::is_slug_request($custom_slug)) {
            return;
        }

        $target_url = rrze_faq_get_redirect_page_url($this->options);
        if (!empty($target_url)) {
            remove_filter('template_redirect', 'redirect_canonical');
        }
    }

    public function maybe_disable_canonical_redirect(): void
    {
        $this->options = $this->getOptions();
        $slug = !empty($this->options['website_custom_faq_slug']) ? sanitize_title($this->options['website_custom_faq_slug']) : 'faq';

        // Nur deaktivieren, wenn eine Weiterleitungsseite gesetzt ist UND exakt der Slug aufgerufen wird
        $redirect_id = (int) ($this->options['website_redirect_archivpage_uri'] ?? 0);
        if ($redirect_id > 0 && self::is_slug_request($slug)) {
            remove_filter('template_redirect', 'redirect_canonical');
        }
    }

    public static function render_custom_404(): void
    {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        include get_404_template();
        exit;
    }

    public function custom_cpt_404_message(): void
    {
        global $wp_query;

        $this->options = $this->getOptions();
        $slug = !empty($this->options['website_custom_faq_slug']) ? sanitize_title($this->options['website_custom_faq_slug']) : 'faq';

        // CPT-Single 404
        if (
            isset($wp_query->query_vars['post_type']) &&
            $wp_query->query_vars['post_type'] === $this->cpt['faq'] &&
            empty($wp_query->post)
        ) {
            self::render_custom_404();
            return;
        }

        // Archiv-Slug direkt aufgerufen?
        if (self::is_slug_request($slug)) {
            $redirect_id = (int) ($this->options['website_redirect_archivpage_uri'] ?? 0);

            if ($redirect_id > 0) {
                $post = get_post($redirect_id);
                if ($post && get_post_status($post) === 'publish') {
                    wp_redirect(esc_url_raw(get_permalink($post)), 301);
                    exit;
                }
            }
            // Andernfalls keine Weiterleitung, Archiv anzeigen lassen
        }
    }





    public function my_custom_allowed_html($allowed_tags, $context)
    {
        if ('post' === $context) {
            // Add the <select> tag and its attributes
            $allowed_tags['select'] = array(
                'name' => true,
                'id' => true,
                'class' => true,
                'multiple' => true,
                'size' => true,
            );

            // Add the <option> tag and its attributes
            $allowed_tags['option'] = array(
                'value' => true,
                'selected' => true,
            );

            // Add the <input> tag and its attributes
            $allowed_tags['input'] = array(
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
            );
        }

        return $allowed_tags;
    }


    public function regularInit()
    {
        $this->setMenu();
        $this->setSections();
        $this->setFields();
        $this->setTabs();

        $this->optionName = Config::getOptionName();
        $this->options = $this->getOptions();
    }

    protected function setMenu()
    {
        $this->settingsMenu = Config::getmenuSettings();
    }

    /**
     * Set setting ranges.
     */
    protected function setSections()
    {
        $this->settingsSections = Config::getSections();
    }

    /**
     * Add a single settings section.
     * @param array $section
     */
    protected function addSection($section)
    {
        $this->settingsSections[] = $section;
    }

    /**
     * Set settings fields.
     */
    protected function setFields()
    {
        $this->settingsFields = Config::getFields();
        if (isset($_GET['page']) && $_GET['page'] == 'rrze-faq' && isset($_GET['current-tab']) && $_GET['current-tab'] == 'faqsync') {
            // Add Sync fields for each domain
            $this->settingsFields['faqsync'] = $this->setSettingsDomains();
        }
    }

    /**
     * Add a single settings field.
     * @param [type] $section [description]
     * @param [type] $field [description]
     */
    protected function addField($section, $field)
    {
        $defaults = array(
            'name' => '',
            'label' => '',
            'desc' => '',
            'type' => 'text'
        );

        $arg = wp_parse_args($field, $defaults);
        $this->settingsFields[$section][] = $arg;
    }

    /**
     * Returns the default settings.
     * @return array
     */
    protected function defaultOptions()
    {
        $options = [];
        foreach ($this->settingsFields as $section => $field) {
            foreach ($field as $option) {
                $name = $option['name'];
                $default = isset($option['default']) ? $option['default'] : '';
                $options = array_merge($options, [$section . '_' . $name => $default]);
            }
        }

        return $options;
    }

    /**
     * Returns the settings.
     * @return array
     */
    public function getOptions()
    {
        $defaults = $this->defaultOptions();

        $options = (array) get_option($this->optionName);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    /**
     * Returns the value of a settings field.
     * @param string $name the name of the settings field
     * @param string $section the name of the section to which the field belongs
     * @param string $default Default text if it is not found
     * @return string
     */
    public function getOption($section, $name, $default = '')
    {
        $option = $section . '_' . $name;

        if (isset($this->options[$option])) {
            return $this->options[$option];
        }

        return $default;
    }

    /**
     * Sanitize callback for the options.
     * @return mixed
     */
    public function sanitizeOptions($options)
    {
        if (!$options) {
            return $options;
        }

        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
            $sanitizeCallback = $this->getSanitizeCallback($key);
            if ($sanitizeCallback) {
                $this->options[$key] = call_user_func($sanitizeCallback, $value);
            }
        }

        return $this->options;
    }

    /**
     * Returns the sanitize callback function for the specified option key.
     * @param string $key Option-Key
     * @return mixed string or (bool) false
     */
    protected function getSanitizeCallback($key = '')
    {
        if (empty($key)) {
            return false;
        }

        foreach ($this->settingsFields as $section => $options) {
            foreach ($options as $option) {
                if ($section . '_' . $option['name'] != $key) {
                    continue;
                }

                return isset($option['sanitize_callback']) && is_callable($option['sanitize_callback']) ? $option['sanitize_callback'] : false;
            }
        }

        return false;
    }

    /**
     * Show settings areas as a tab.
     * Shows all labels of the settings areas as a tab.
     */
    public function showTabs()
    {
        $html = '<h1>' . esc_html($this->settingsMenu['title']) . '</h1>' . PHP_EOL;

        if (count($this->settingsSections) < 2) {
            return;
        }

        $html .= '<h2 class="nav-tab-wrapper wp-clearfix">';

        foreach ($this->settingsSections as $section) {
            $class = $section['id'] == $this->currentTab ? 'nav-tab-active' : $this->defaultTab;
            $html .= sprintf(
                '<a href="?page=%4$s&current-tab=%1$s" class="nav-tab %3$s" id="%1$s-tab">%2$s</a>',
                esc_attr($section['id']),
                esc_html($section['title']),
                esc_attr($class),
                esc_attr($this->settingsMenu['menu_slug'])
            );
        }

        $html .= '</h2>' . PHP_EOL;

        echo wp_kses_post($html);
    }

    /**
     * Display the settings areas.
     * Displays the corresponding form for each settings area.
     */
    public function showSections()
    {
        foreach ($this->settingsSections as $section) {
            if ($section['id'] != $this->currentTab) {
                continue;
            }
            $btn_label = '';
            $get = '';

            switch ($this->currentTab) {
                case 'faqsync':
                    $get = '?sync';
                    break;
                case 'doms':
                    $btn_label = esc_html__('Add domain', 'rrze-faq');
                    $get = '?doms';
                    break;
                case 'faqlog':
                    $btn_label = esc_html__('Delete logfile', 'rrze-faq');
                    $get = '?del';
                    break;
            }

            echo '<div id="' . esc_attr($section['id']) . '">';
            echo '<form method="post" action="options.php' . esc_attr($get) . '">';
            settings_fields($section['id']);
            do_settings_sections($section['id']);
            submit_button(esc_html($btn_label));
            if ($this->currentTab == 'doms') {
                $this->domainOutput();
            }
            echo '</form>';
            echo '</div>';
        }
    }

    /**
     * Page output options
     */
    public function pageOutput()
    {
        echo '<div class="wrap">', PHP_EOL;
        $this->showTabs();
        $this->showSections();
        echo '</div>', PHP_EOL;
    }

    public function domainOutput()
    {
        $api = new API();
        $aDomains = $api->getDomains();

        if (count($aDomains) > 0) {
            $i = 1;
            echo '<style> .settings_page_rrze-faq #log .form-table th {width:0;}</style>';
            echo '<table class="wp-list-table widefat striped"><thead><tr><th colspan="3">' . esc_html__('Domains:', 'rrze-faq') . '</th></tr></thead><tbody>';
            foreach ($aDomains as $name => $url) {
                echo '<tr><td><input type="checkbox" name="del_domain_' . esc_attr($i) . '" value="' . esc_url($url) . '"></td><td>' . esc_html($name) . '</td><td>' . esc_url($url) . '</td></tr>';
                $i++;
            }
            echo '</tbody></table>';
            echo '<p>' . esc_html__('Please note: "Delete selected domains" will DELETE every FAQ on this website that has been fetched from the selected domains.', 'rrze-faq') . '</p>';
            submit_button(esc_html__('Delete selected domains', 'rrze-faq'));
        }
    }

    public function setSettingsDomains()
    {
        $i = 1;
        $newFields = array();
        $api = new API();
        $additionalfields = array();

        $aDomains = $api->getDomains();

        // foreach ( $this->domains as $shortname => $url ){
        foreach ($aDomains as $shortname => $url) {
            $aCategories = $api->getCategories($url, $shortname);
            foreach ($this->settingsFields['faqsync'] as $field) {
                if ($field['name'] == 'autosync' || $field['name'] == 'frequency' || $field['name'] == 'info') {
                    if ($i == 1) {
                        $additionalfields[] = $field;
                    }
                    continue;
                }
                switch ($field['name']) {
                    case 'shortname':
                        $field['default'] = $shortname;
                        break;
                    case 'url':
                        $field['default'] = $url;
                        break;
                    case 'categories':
                        if (!$aCategories) {
                            $field['options'][''] = __('no category with source = "website" found', 'rrze-faq');
                        }
                        foreach ($aCategories as $slug => $name) {
                            $field['options'][$slug] = $name;
                        }
                        break;
                }
                $field['name'] = $field['name'] . '_' . $shortname;
                $newFields[] = $field;
            }
            $i++;
        }
        foreach ($additionalfields as $addfield) {
            $newFields[] = $addfield;
        }
        return $newFields;
    }

    /**
     * Creates the context help for the settings page.
     */
    public function adminHelpTab()
    {
        $screen = get_current_screen();

        if (!method_exists($screen, 'add_help_tab') || $screen->id != $this->optionsPage) {
            return;
        }

        $helpTab = getHelpTab();

        if (empty($helpTab)) {
            return;
        }

        foreach ($helpTab as $help) {
            $screen->add_help_tab(
                [
                    'id' => $help['id'],
                    'title' => $help['title'],
                    'content' => implode(PHP_EOL, $help['content'])
                ]
            );
            $screen->set_help_sidebar($help['sidebar']);
        }
    }

    /**
     * Initialization and registration of areas and fields.
     */
    public function adminInit()
    {
        add_filter('wp_kses_allowed_html', [$this, 'my_custom_allowed_html'], 10, 2);

        // Adding setting areas
        foreach ($this->settingsSections as $section) {
            if (isset($section['desc']) && !empty($section['desc'])) {
                $section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';
                $callback = function () use ($section) {
                    echo wp_kses_post(str_replace('"', '\"', $section['desc']));
                };
            } elseif (isset($section['callback'])) {
                $callback = $section['callback'];
            } else {
                $callback = null;
            }

            add_settings_section($section['id'], $section['title'], $callback, $section['id']);
        }

        // Add settings fields
        foreach ($this->settingsFields as $section => $field) {
            foreach ($field as $option) {
                $name = $option['name'];
                $type = isset($option['type']) ? $option['type'] : 'text';
                $label = isset($option['label']) ? $option['label'] : '';
                $callback = isset($option['callback']) ? $option['callback'] : [$this, 'callback' . ucfirst($type)];

                $args = [
                    'id' => $name,
                    'class' => isset($option['class']) ? $option['class'] : $name,
                    'label_for' => "{$section}[{$name}]",
                    'desc' => isset($option['desc']) ? $option['desc'] : '',
                    'name' => $label,
                    'section' => $section,
                    'size' => isset($option['size']) ? $option['size'] : null,
                    'options' => isset($option['options']) ? $option['options'] : '',
                    'default' => isset($option['default']) ? $option['default'] : '',
                    'sanitize_callback' => isset($option['sanitize_callback']) ? $option['sanitize_callback'] : '',
                    'type' => $type,
                    'placeholder' => isset($option['placeholder']) ? $option['placeholder'] : '',
                    'min' => isset($option['min']) ? $option['min'] : '',
                    'max' => isset($option['max']) ? $option['max'] : '',
                    'step' => isset($option['step']) ? $option['step'] : '',
                ];

                add_settings_field("{$section}[{$name}]", $label, $callback, $section, $section, $args);

                if (in_array($type, ['color', 'file'])) {
                    add_action('admin_enqueue_scripts', [$this, $type . 'EnqueueScripts']);
                }
            }
        }

        // Register the settings
        foreach ($this->settingsSections as $section) {
            register_setting($section['id'], $this->optionName, [$this, 'sanitizeOptions']);
        }
    }


    /**
     * Add the options page
     * @return void
     */
    public function adminMenu()
    {
        $this->optionsPage = add_options_page(
            $this->settingsMenu['page_title'],
            $this->settingsMenu['menu_title'],
            $this->settingsMenu['capability'],
            $this->settingsMenu['menu_slug'],
            [$this, 'pageOutput']
        );

        // add_action('load-' . $this->optionsPage, [$this, 'adminHelpTab']);
    }

    /**
     * Set tabs
     */
    protected function setTabs()
    {
        foreach ($this->settingsSections as $key => $val) {
            if ($key == 0) {
                $this->defaultTab = $val['id'];
            }
            $this->allTabs[] = $val['id'];
        }

        $this->currentTab = array_key_exists('current-tab', $_GET) && in_array(sanitize_text_field(wp_unslash($_GET['current-tab'])), $this->allTabs) ? sanitize_text_field(wp_unslash($_GET['current-tab'])) : $this->defaultTab;
    }

    /**
     * Enqueue scripts and style
     * @return void
     */
    public function adminEnqueueScripts()
    {
        wp_register_script('wp-color-picker-settings', plugins_url('assets/js/wp-color-picker.js', plugin_basename($this->pluginFile)));
        wp_register_script('wp-media-settings', plugins_url('assets/js/wp-media.js', plugin_basename($this->pluginFile)));
    }

    /**
     * Enqueue WP color picker scripts.
     * @return [type] [description]
     */
    public function colorEnqueueScripts()
    {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_script('wp-color-picker-settings');
        wp_enqueue_script('jquery');
    }

    /**
     * Enqueue WP-Media scripts.
     * @return [type] [description]
     */
    public function fileEnqueueScripts()
    {
        wp_enqueue_media();
        wp_enqueue_script('wp-media-settings');
        wp_enqueue_script('jquery');
    }

    /**
     * Returns the field description of the settings field.
     * @param array $args Arguments of the settings field
     */
    public function getFieldDescription($args)
    {
        if (!empty($args['desc'])) {
            $desc = sprintf('<p class="description">%s</p>', $args['desc']);
        } else {
            $desc = '';
        }

        return $desc;
    }

    /**
     * Displays a text field for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackText($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? esc_attr($args['size']) : 'regular';
        $type = isset($args['type']) ? esc_attr($args['type']) : 'text';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . esc_attr($args['placeholder']) . '"';

        $html = sprintf(
            '<input type="%1$s" class="%2$s-text" id="%4$s-%5$s" name="%3$s[%4$s_%5$s]" value="%6$s"%7$s>',
            $type,
            $size,
            esc_attr($this->optionName),
            esc_attr($args['section']),
            esc_attr($args['id']),
            esc_attr($value),
            $placeholder
        );
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }


    /**
     * Displays a number field for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackNumber($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $type = isset($args['type']) ? $args['type'] : 'number';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';
        $min = ($args['min'] == '') ? '' : ' min="' . $args['min'] . '"';
        $max = ($args['max'] == '') ? '' : ' max="' . $args['max'] . '"';
        $step = ($args['step'] == '') ? '' : ' step="' . $args['step'] . '"';

        $html = sprintf(
            '<input type="%1$s" class="%2$s-number" id="%4$s-%5$s" name="%3$s[%4$s_%5$s]" value="%6$s"%7$s%8$s%9$s%10$s>',
            $type,
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value,
            $placeholder,
            $min,
            $max,
            $step
        );
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }

    /**
     * Displays a checkbox for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackCheckbox($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));

        $html = '<fieldset>';
        $html .= sprintf(
            '<label for="%1$s-%2$s">',
            $args['section'],
            $args['id']
        );
        $html .= sprintf(
            '<input type="hidden" name="%1$s[%2$s_%3$s]" value="off">',
            $this->optionName,
            $args['section'],
            $args['id']
        );
        $html .= sprintf(
            '<input type="checkbox" class="checkbox" id="%2$s-%3$s" name="%1$s[%2$s_%3$s]" value="on" %4$s>',
            $this->optionName,
            $args['section'],
            $args['id'],
            checked($value, 'on', false)
        );
        $html .= sprintf(
            '%1$s</label>',
            $args['desc']
        );
        $html .= '</fieldset>';

        echo wp_kses_post($html);
    }

    /**
     * Displays a multicheckbox for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackMulticheck($args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $html = '<fieldset>';
        $html .= sprintf(
            '<input type="hidden" name="%1$s[%2$s_%3$s]" value="">',
            $this->optionName,
            $args['section'],
            $args['id']
        );
        foreach ($args['options'] as $key => $label) {
            $checked = isset($value[$key]) ? $value[$key] : '0';
            $html .= sprintf(
                '<label for="%1$s-%2$s-%3$s">',
                $args['section'],
                $args['id'],
                $key
            );
            $html .= sprintf(
                '<input type="checkbox" class="checkbox" id="%2$s-%3$s-%4$s" name="%1$s[%2$s_%3$s][%4$s]" value="%4$s" %5$s>',
                $this->optionName,
                $args['section'],
                $args['id'],
                $key,
                checked($checked, $key, false)
            );
            $html .= sprintf('%1$s</label><br>', $label);
        }

        $html .= $this->getFieldDescription($args);
        $html .= '</fieldset>';

        echo wp_kses_post($html);
    }

    /**
     * Displays a radio button for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackRadio($args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $html = '<fieldset>';

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<label for="%1$s-%2$s-%3$s">',
                $args['section'],
                $args['id'],
                $key
            );
            $html .= sprintf(
                '<input type="radio" class="radio" id="%2$s-%3$s-%4$s" name="%1$s[%2$s_%3$s]" value="%4$s" %5$s>',
                $this->optionName,
                $args['section'],
                $args['id'],
                $key,
                checked($value, $key, false)
            );
            $html .= sprintf(
                '%1$s</label><br>',
                $label
            );
        }

        $html .= $this->getFieldDescription($args);
        $html .= '</fieldset>';

        echo wp_kses_post($html);
    }

    /**
     * Displays a selection list (select box) for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackMultiSelect($args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $value = ($value ? $value : array());
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $html = sprintf(
            '<select class="%1$s" id="%3$s-%4$s" name="%2$s[%3$s_%4$s][]" multiple="multiple">',
            $size,
            $this->optionName,
            $args['section'],
            $args['id']
        );

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $key,
                selected(true, in_array($key, $value), false),
                $label
            );
        }

        $html .= sprintf('</select>');
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }


    /**
     * Displays a selection list (select box) for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackSelect($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $html = sprintf(
            '<select class="%1$s" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]">',
            $size,
            $this->optionName,
            $args['section'],
            $args['id']
        );

        foreach ($args['options'] as $key => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $key,
                selected($value, $key, false),
                $label
            );
        }

        $html .= sprintf('</select>');
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }

    /**
     * Displays a text field for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackTextarea($args)
    {
        $value = esc_textarea($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';

        $html = sprintf(
            '<textarea rows="5" cols="55" class="%1$s-text" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]"%5$s>%6$s</textarea>',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $placeholder,
            $value
        );
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }

    /**
     * Displays a rich text text field (WP editor) for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackWysiwyg($args)
    {
        $value = $this->getOption($args['section'], $args['id'], $args['default']);
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : '500px';

        echo wp_kses_post('<div style="max-width: ' . $size . ';">');

        $editor_settings = [
            'teeny' => true,
            'textarea_name' => sprintf('%1$s[%2$s_%3$s]', $this->optionName, $args['section'], $args['id']),
            'textarea_rows' => 10
        ];

        if (isset($args['options']) && is_array($args['options'])) {
            $editor_settings = array_merge($editor_settings, $args['options']);
        }

        wp_editor($value, $args['section'] . '-' . $args['id'], $editor_settings);

        echo '</div>';

        echo wp_kses_post($this->getFieldDescription($args));
    }

    /**
     * Displays a file upload field for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackFile($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $id = $args['section'] . '[' . $args['id'] . ']';
        $label = isset($args['options']['button_label']) ? $args['options']['button_label'] : __('Choose File', 'rrze-faq');

        $html = sprintf(
            '<input type="text" class="%1$s-text settings-media-url" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]" value="%5$s"/>',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value
        );
        $html .= '<input type="button" class="button settings-media-browse" value="' . $label . '">';
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }

    /**
     * Displays a password field for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackPassword($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';

        $html = sprintf(
            '<input type="password" class="%1$s-text" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]" value="%5$s">',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value
        );
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }

    /**
     * Displays a color picker field (WP-Color-Picker) for a settings field.
     * @param array $args Arguments of the settings field
     */
    public function callbackColor($args)
    {
        $value = esc_attr($this->getOption($args['section'], $args['id'], $args['default']));
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';

        $html = sprintf(
            '<input type="text" class="%1$s-text wp-color-picker-field" id="%3$s-%4$s" name="%2$s[%3$s_%4$s]" value="%5$s" data-default-color="%6$s">',
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value,
            $args['default']
        );
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }

    public function callbackHidden($args)
    {
        $value = time();
        $size = isset($args['size']) && !is_null($args['size']) ? $args['size'] : 'regular';
        $type = 'hidden';
        $placeholder = empty($args['placeholder']) ? '' : ' placeholder="' . $args['placeholder'] . '"';

        $html = sprintf(
            '<input type="%1$s" class="%2$s-text" id="%4$s-%5$s" name="%3$s[%4$s_%5$s]" value="%6$s"%7$s>',
            $type,
            $size,
            $this->optionName,
            $args['section'],
            $args['id'],
            $value,
            $placeholder
        );
        $html .= $this->getFieldDescription($args);

        echo wp_kses_post($html);
    }

    public function callbackLogfile($args)
    {
        if (file_exists($args['default'])) {
            $lines = file($args['default']);
            if ($lines !== false) {
                echo '<style> .settings_page_rrze-faq #faqlog .form-table th {width:0;}</style><table class="wp-list-table widefat striped"><tbody>';
                foreach ($lines as $line) {
                    echo wp_kses_post('<tr><td>' . $line . '</td></tr>');
                }
                echo '</tbody></table>';
            } else {
                echo esc_html(__('Logfile is empty.', 'rrze-faq'));
            }
        } else {
            echo esc_html(__('Logfile is empty.', 'rrze-faq'));
        }
    }

    public function callbackPlaintext($args)
    {
        echo '<strong>' . esc_html($this->getOption($args['section'], $args['id'], $args['default'])) . '</strong>';
    }

    public function callbackLine()
    {
        echo '<hr>';
    }

    public function callbackButton($args)
    {
        submit_button($this->getOption($args['section'], $args['id'], $args['default']));
    }



}
