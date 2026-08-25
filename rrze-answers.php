<?php

/*
Plugin Name:        RRZE Answers
Plugin URI:         https://github.com/RRZE-Webteam/rrze-answers
Version:            1.4.7
Description:        Explain your content with FAQ, glossary, synonyms and placeholder.
Author:             RRZE Webteam
Author URI:         https://www.wp.rrze.fau.de/
License:            GNU General Public License Version 3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:        rrze-answers
Domain Path:        /languages
Requires at least:  6.8
Requires PHP:       8.2
*/

namespace RRZE\Answers;

use RRZE\Answers\Main;
use RRZE\Answers\Common\Plugin\Plugin;

defined('ABSPATH') || exit;

/**
 * ------------------------------------------------------------
 * PSR-4-ish autoloader for /includes
 * ------------------------------------------------------------
 */
spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $baseDir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Normal bootstrap.
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

function plugin(): Plugin
{
    static $instance;

    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }

    return $instance;
}

function main(): Main
{
    static $instance;

    if (null === $instance) {
        $instance = new Main();
    }

    return $instance;
}

function load_plugin_translations(): void
{
    $domain = 'rrze-answers';
    $locale = determine_locale();
    $mofile = plugin()->getPath() . 'languages/' . $domain . '-' . $locale . '.mo';

    if (!is_readable($mofile) && str_starts_with($locale, 'de')) {
        $mofile = plugin()->getPath() . 'languages/' . $domain . '-de_DE.mo';
    }

    if (is_readable($mofile)) {
        \unload_textdomain($domain);
        \load_textdomain($domain, $mofile);

        return;
    }

    \load_plugin_textdomain(
        $domain,
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

function register_blocks(): void
{
    register_block_type_from_metadata(__DIR__ . '/blocks/faq');
    register_block_type_from_metadata(__DIR__ . '/blocks/faq-widget');
    register_block_type_from_metadata(__DIR__ . '/blocks/glossary');
    register_block_type_from_metadata(__DIR__ . '/blocks/synonym');
    register_block_type_from_metadata(__DIR__ . '/blocks/placeholder');

    $faq_handle = generate_block_asset_handle('rrze-answers/faq', 'editorScript');
    $faq_widget_handle = generate_block_asset_handle('rrze-answers/faq-widget', 'editorScript');
    $glossary_handle = generate_block_asset_handle('rrze-answers/glossary', 'editorScript');
    $synonym_handle = generate_block_asset_handle('rrze-answers/synonym', 'editorScript');
    $placeholder_handle = generate_block_asset_handle('rrze-answers/placeholder', 'editorScript');

    $path = plugin_dir_path(__FILE__) . 'languages';

    wp_set_script_translations($faq_handle, 'rrze-answers', $path);
    wp_set_script_translations($faq_widget_handle, 'rrze-answers', $path);
    wp_set_script_translations($glossary_handle, 'rrze-answers', $path);
    wp_set_script_translations($synonym_handle, 'rrze-answers', $path);
    wp_set_script_translations($placeholder_handle, 'rrze-answers', $path);
}

/**
 * Handle the loading of the plugin.
 */
function loaded(): void
{
    // Trigger the 'loaded' method of the main plugin instance.
    plugin()->loaded();

    // WordPress 6.7+ expects translation loading at init or later. Load the
    // textdomain immediately before translated CPT labels are constructed.
    add_action('init', __NAMESPACE__ . '\load_plugin_translations', -2);

    $wpCompatibe = is_wp_version_compatible(plugin()->getRequiresWP());
    $phpCompatible = is_php_version_compatible(plugin()->getRequiresPHP());

    // Check system requirements.
    if (!$wpCompatibe || !$phpCompatible) {
        add_action('init', function () use ($wpCompatibe, $phpCompatible) {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            $pluginName = plugin()->getName();

            $error = '';
            if (!$wpCompatibe) {
                $error = sprintf(
                    __('The server is running WordPress version %1$s. The plugin requires at least WordPress version %2$s.', 'rrze-answers'),
                    wp_get_wp_version(),
                    plugin()->getRequiresWP()
                );
            } elseif (!$phpCompatible) {
                $error = sprintf(
                    __('The server is running PHP version %1$s. The plugin requires at least PHP version %2$s.', 'rrze-answers'),
                    PHP_VERSION,
                    plugin()->getRequiresPHP()
                );
            }

            add_action('admin_notices', function () use ($pluginName, $error) {
                printf(
                    '<div class="notice notice-error"><p>' .
                    esc_html__('Plugins: %1$s: %2$s', 'rrze-answers') .
                    '</p></div>',
                    esc_html($pluginName),
                    esc_html($error)
                );
            });
        });

        return;
    }

    // Initialize plugin.
    main();

    add_action('init', __NAMESPACE__ . '\register_blocks');
}
