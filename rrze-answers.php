<?php

/*
Plugin Name:        RRZE Answers
Plugin URI:         https://github.com/RRZE-Webteam/rrze-answers
Version:            0.0.61
Description:        Explain your content with FAQ, glossary and placeholders. 
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

// Prevent direct access to the file.
// This line ensures that the file is only executed within the context of WordPress.
// If accessed directly, it will exit the script to prevent unauthorized access.
defined('ABSPATH') || exit;

$s = array(
    '/^((http|https):\/\/)?(www.)+/i',
    '/\//',
    '/[^A-Za-z0-9\-]/',
);
$r = array(
    '',
    '-',
    '-',
);

define('ANSWERSLOGFILE', plugin_dir_path(__FILE__) . 'rrze-answers-' . preg_replace($s, $r, get_bloginfo('url')) . '.log');


/**
 * SPL Autoloader (PSR-4).
 * 
 * This autoloader function is registered with the SPL autoload stack to automatically load classes
 * from the plugin's 'includes' directory based on their fully-qualified class names.
 * It follows the PSR-4 autoloading standard, where the namespace corresponds to the directory structure.
 * It maps the namespace prefix to the base directory of the plugin, allowing for easy class loading
 * without the need for manual `require` or `include` statements.
 * This autoloader is particularly useful for organizing plugin code into classes and namespaces,
 * promoting better code structure and maintainability.
 * Use require_once `vendor/autoload.php` instead if you are using Composer for autoloading.
 * 
 * @see https://www.php-fig.org/psr/psr-4/
 * @param string $class The fully-qualified class name.
 * @return void
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


// Register activation hook for the plugin
register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');

// Register deactivation hook for the plugin
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');

/**
 * Add an action hook for the 'plugins_loaded' hook.
 *
 * This hook is triggered after all active plugins have been loaded, allowing the plugin to perform
 * initialization tasks.
 */
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * Activation callback function.
 * 
 * @return void
 */
function activation()
{
    // Use this if you need to perform tasks on activation.
}

/**
 * Deactivation callback function.
 */
function deactivation()
{
    // Use this if you need to perform tasks on deactivation.
    // For example, you might want to clean up options or scheduled events.
}

/**
 * Singleton pattern for initializing and accessing the main plugin instance.
 *
 * This method ensures that only one instance of the Plugin class is created and returned.
 *
 * @return Plugin The main instance of the Plugin class.
 */
function plugin()
{
    // Declare a static variable to hold the instance.
    static $instance;

    // Check if the instance is not already created.
    if (null === $instance) {
        // Add a new instance of the Plugin class, passing the current file (__FILE__) as a parameter.
        $instance = new Plugin(__FILE__);
    }

    // Return the main instance of the Plugin class.
    return $instance;
}

/**
 * Main function to initialize the plugin.
 *
 * This function follows the singleton pattern to ensure that only one instance of the Main class is created.
 * It serves as the entry point for the plugin's functionality and is called when the plugin is loaded.
 *
 * @return Main The main instance of the Main class.
 */
function main()
{
    // Declare a static variable to hold the instance.
    static $instance;

    // Check if the instance is not already created.
    if (null === $instance) {
        // Add a new instance of the Main class.
        $instance = new Main();
    }

    // Return the main instance of the Main class.
    return $instance;
}

/**
 * Callback function to load the plugin textdomain.
 * 
 * @return void
 */
function load_textdomain()
{
    load_plugin_textdomain(
        'rrze-answers',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
function register_blocks()
{
    register_block_type_from_metadata(__DIR__ . '/blocks/faq');
    register_block_type_from_metadata(__DIR__ . '/blocks/glossary');
    register_block_type_from_metadata(__DIR__ . '/blocks/placeholder');

    // $script_handle = generate_block_asset_handle('rrze-answers/faq', 'editorScript');
    // wp_set_script_translations($script_handle, 'rrze-answers', plugin_dir_path(__FILE__) . 'languages');
}


function rrze_update_glossary_cpt()
{
    global $wpdb;

    if (get_option('rrze_update_glossary_cpt_done')) {
        return;
    }

    $wpdb->query("
        UPDATE {$wpdb->term_taxonomy} tt
        INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        SET tt.taxonomy = 'rrze_glossary_category'
        WHERE p.post_type = 'glossary'
        AND tt.taxonomy = 'glossary_category'
    ");

    $wpdb->query("
        UPDATE {$wpdb->term_taxonomy} tt
        INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        SET tt.taxonomy = 'rrze_glossary_tag'
        WHERE p.post_type = 'glossary'
        AND tt.taxonomy = 'glossary_tag'
    ");

    $wpdb->update(
        $wpdb->posts,
        ['post_type' => 'rrze_glossary'],
        ['post_type' => 'glossary']
    );

    wp_cache_flush();
    flush_rewrite_rules();

    update_option('rrze_update_glossary_cpt_done', 1);
}

function rrze_update_placeholder_cpt()
{
    global $wpdb;

    if (get_option('rrze_update_placeholder_cpt_done')) {
        return;
    }

    $wpdb->update(
        $wpdb->posts,
        ['post_type' => 'rrze_placeholder'],
        ['post_type' => 'placeholder']
    );

    wp_cache_flush();
    flush_rewrite_rules();

    update_option('rrze_update_placeholder_cpt_done', 1);
}


// 1. activate rrze-answers on all websites where rrze-faq, rrze-glossary or rrze-placeholder is active
// 2. deaktivate rrze-faq, rrze-glossary and rrze-placeholder
function rrze_answers_migrate_multisite() {
    if ( get_site_option('rrze-answers_migrate_multisite_done') ) return;
    if ( ! is_multisite() || ! is_network_admin() || ! current_user_can('manage_network_plugins') ) return;
    if ( ! function_exists('is_plugin_active') ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( ! function_exists('activate_plugin') ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( ! function_exists('deactivate_plugins') ) require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $targets = [
        'rrze-faq/rrze-faq.php',
        'rrze-glossary/rrze-glossary.php',
        'rrze-placeholder/rrze-placeholder.php',
    ];
    $answers = 'rrze-answers/rrze-answers.php';
    $report  = [];

    foreach ( get_sites(['number' => 0]) as $site ) {
        switch_to_blog( (int) $site->blog_id );

        $has_target = false;
        foreach ( $targets as $p ) { if ( is_plugin_active($p) ) { $has_target = true; break; } }

        if ( $has_target ) {
            if ( ! is_plugin_active( $answers ) ) {
                $res = activate_plugin( $answers, '', false, false );
                if ( is_wp_error( $res ) || ! is_plugin_active( $answers ) ) {
                    restore_current_blog();
                    continue;
                }
            }

            $deactivated = [];
            foreach ( $targets as $p ) {
                if ( is_plugin_active( $p ) ) {
                    deactivate_plugins( $p, false );
                    $deactivated[] = dirname( $p );
                }
            }

            if ( $deactivated ) {
                $report[] = [
                    'label' => get_bloginfo('name') . ' (' . home_url() . ')',
                    'list'  => $deactivated,
                ];
            }
        }

        restore_current_blog();
    }

    add_action('network_admin_notices', function () use ($report) {
        if ( $report ) {
            echo '<div class="notice notice-success"><p><strong>RRZE-Answers</strong> ' . esc_html__( 'was activated and old plugins were deactivated on these sites:', 'rrze-answers' ) . '</p><ul style="margin-left:1.2em">';
            foreach ( $report as $row ) {
                echo '<li>' . esc_html( $row['label'] ) . ': ' . esc_html( implode(', ', $row['list']) ) . '</li>';
            }
            echo '</ul></div>';
        } else {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No sites required changes.', 'rrze-answers' ) . '</p></div>';
        }
    });

    update_site_option('rrze-answers_migrate_multisite_done', 1);
}


/**
 * Handle the loading of the plugin.
 *
 * This function is responsible for initializing the plugin, loading text domains for localization,
 * checking system requirements, and displaying error notices if necessary.
 */
function loaded()
{
    // Trigger the 'loaded' method of the main plugin instance.
    plugin()->loaded();

    // Load the plugin textdomain for translations.
    add_action(
        'init',
        __NAMESPACE__ . '\load_textdomain'
    );

    $wpCompatibe = is_wp_version_compatible(plugin()->getRequiresWP());
    $phpCompatible = is_php_version_compatible(plugin()->getRequiresPHP());

    // Check system requirements.
    if (!$wpCompatibe || !$phpCompatible) {
        // If the system requirements are not met, add an action to display an admin notice.
        add_action('init', function () use ($wpCompatibe, $phpCompatible) {
            // Check if the current user has the capability to activate plugins.
            if (current_user_can('activate_plugins')) {
                // Get the plugin name for display in the admin notice.
                $pluginName = plugin()->getName();

                // Determine the appropriate admin notice tag based on whether the plugin is network activated.
                $tag = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';

                $error = '';
                if (!$wpCompatibe) {
                    $error = sprintf(
                        /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
                        __('The server is running WordPress version %1$s. The plugin requires at least WordPress version %2$s.', 'rrze-answers'),
                        wp_get_wp_version(),
                        plugin()->getRequiresWP()
                    );
                } elseif (!$phpCompatible) {
                    $error = sprintf(
                        /* translators: 1: Server PHP version number, 2: Required PHP version number. */
                        __('The server is running PHP version %1$s. The plugin requires at least PHP version %2$s.', 'rrze-answers'),
                        PHP_VERSION,
                        plugin()->getRequiresPHP()
                    );
                }

                // Display the error notice in the admin area.
                // This will show a notice with the plugin name and the error message.
                add_action('admin_notices', function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' .
                        /* translators: 1: The plugin name, 2: The error string. */
                        esc_html__('Plugins: %1$s: %2$s', 'rrze-answers') .
                        '</p></div>',
                        $pluginName,
                        $error
                    );
                });
            }
        });

        // If the system requirements are not met, the plugin initialization will not proceed.
        return;
    }

    // If system requirements are met, proceed to initialize the main plugin instance.
    // This will load the main functionality of the plugin.
    main();

    add_action('init', __NAMESPACE__ . '\register_blocks');
    add_action('init', __NAMESPACE__ . '\rrze_update_glossary_cpt');
    add_action('init', __NAMESPACE__ . '\rrze_update_placeholder_cpt');
    add_action('network_admin_init', __NAMESPACE__ . '\rrze-answers_migrate_multisite');
}
