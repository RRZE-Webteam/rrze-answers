<?php

/*
Plugin Name:        RRZE Answers
Plugin URI:         https://github.com/RRZE-Webteam/rrze-answers
Version:            1.0.20
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
use RRZE\Answers\Common\Tools;
use RRZE\Answers\Common\Plugin\Plugin;

const RRZE_ANSWERS_PLUGIN = 'rrze-answers/rrze-answers.php';
const MIGRATE_DONE_KEY = 'rrze_answers_migrate_multisite_done';
const MIGRATE_PENDING_KEY = 'rrze_answers_migrate_multisite_pending';
const MIGRATE_REPORT_KEY = 'rrze_answers_migrate_multisite_report';

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
    register_block_type_from_metadata(__DIR__ . '/blocks/faq-widget');
    register_block_type_from_metadata(__DIR__ . '/blocks/glossary');
    register_block_type_from_metadata(__DIR__ . '/blocks/placeholder');


    $faq_handle = generate_block_asset_handle('rrze-answers/faq', 'editorScript');
    $faq_widget_handle = generate_block_asset_handle('rrze-answers/faq-widget', 'editorScript');
    $glossary_handle = generate_block_asset_handle('rrze-answers/glossary', 'editorScript');
    $placeholder_handle = generate_block_asset_handle('rrze-answers/placeholder', 'editorScript');

    $path = plugin_dir_path(__FILE__) . 'languages';

    wp_set_script_translations($faq_handle, 'rrze-answers', $path);
    wp_set_script_translations($faq_widget_handle, 'rrze-answers', $path);
    wp_set_script_translations($glossary_handle, 'rrze-answers', $path);
    wp_set_script_translations($placeholder_handle, 'rrze-answers', $path);
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


function rrze_migrate_domains()
{
    if (get_option('rrze_migrate_domains_done')) {
        return;
    }

    $domains = [];
    $source_options = ['rrze-faq', 'rrze-glossary'];

    foreach ($source_options as $option_name) {
        $option = get_option($option_name);

        if (!empty($option['registeredDomains'])) {
            foreach ($option['registeredDomains'] as $shortname => $url) {
                $identifier = Tools::getIdentifier($url);
                $domains[$identifier] = $url;
            }
        }
    }

    $answers_option = get_option('rrze-answers', []);

    $answers_option['registeredDomains'] = $domains;

    delete_option('rrze-answers');
    add_option('rrze-answers', $answers_option);

    update_option('rrze_migrate_domains_done', 1);
}



/**
 * Old plugins to replace.
 */
function rrze_answers_migrate_targets(): array
{
    return [
        'rrze-faq/rrze-faq.php',
        'rrze-glossary/rrze-glossary.php',
        'rrze-synonym/rrze-synonym.php',
    ];
}

/**
 * Ensure plugin functions are available in admin context.
 */
function rrze_answers_ensure_plugin_functions(): void
{
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
}

/**
 * Aggressively refresh plugin caches / site option caches.
 * Helps with persistent object cache edge cases.
 */
function rrze_answers_refresh_plugin_caches(): void
{
    if (function_exists('wp_clean_plugins_cache')) {
        wp_clean_plugins_cache(true);
    }

    // active_sitewide_plugins is stored in the site-options cache group.
    wp_cache_delete('active_sitewide_plugins', 'site-options');
    wp_cache_delete('active_plugins', 'options');
}

/**
 * One-time migration report notice in Network Admin.
 */
function rrze_answers_migrate_multisite_notice(): void
{
    if (!is_multisite() || !is_network_admin()) {
        return;
    }

    $payload = get_site_transient(MIGRATE_REPORT_KEY);
    if (empty($payload) || !is_array($payload)) {
        return;
    }

    delete_site_transient(MIGRATE_REPORT_KEY);

    $type = $payload['type'] ?? 'info'; // info|success|warning|error
    $title = $payload['title'] ?? 'RRZE-Answers';
    $intro = $payload['intro'] ?? '';
    $items = $payload['items'] ?? [];
    $footer = $payload['footer'] ?? '';

    $class = match ($type) {
        'success' => 'notice notice-success',
        'warning' => 'notice notice-warning',
        'error' => 'notice notice-error',
        default => 'notice notice-info',
    };

    echo '<div class="' . esc_attr($class) . '"><p><strong>' . esc_html($title) . '</strong>';
    if ($intro !== '') {
        echo ' ' . esc_html($intro);
    }
    echo '</p>';

    if (!empty($items)) {
        echo '<ul style="margin-left:1.2em">';
        foreach ($items as $row) {
            echo '<li>' . wp_kses_post($row) . '</li>';
        }
        echo '</ul>';
    }

    if ($footer !== '') {
        echo '<p>' . esc_html($footer) . '</p>';
    }

    echo '</div>';
}

/**
 * Activation hook.
 *
 * IMPORTANT:
 * On multisite, this callback receives $network_wide.
 * We use it to detect "Network Activate" deterministically.
 */
function rrze_answers_on_activate(bool $network_wide): void
{
    if (!is_multisite() || !$network_wide) {
        return;
    }

    // Ensure plugin APIs exist.
    rrze_answers_ensure_plugin_functions();

    /**
     * CRITICAL:
     * RRZE-Answers must NOT stay network-activated.
     * Otherwise it will be active on all sites automatically,
     * which breaks the "activate only where needed" migration.
     *
     * So we immediately deactivate it network-wide,
     * then schedule the per-site migration for the next request.
     */
    if (is_plugin_active_for_network(RRZE_ANSWERS_PLUGIN)) {
        deactivate_plugins(RRZE_ANSWERS_PLUGIN, false, true);
        rrze_answers_refresh_plugin_caches();
    }

    // If it still remains network-active, we cannot proceed safely.
    if (is_plugin_active_for_network(RRZE_ANSWERS_PLUGIN)) {
        set_site_transient(
            MIGRATE_REPORT_KEY,
            [
                'type' => 'error',
                'title' => 'RRZE-Answers',
                'intro' => __('Migration aborted: RRZE-Answers could not be deactivated network-wide during activation.', 'rrze-answers'),
                'items' => [],
                'footer' => __('No site changes were made. Please deactivate RRZE-Answers network-wide manually and retry.', 'rrze-answers'),
            ],
            10 * MINUTE_IN_SECONDS
        );
        // Do NOT set pending, do NOT set done.
        return;
    }

    // Mark migration as pending (so we run it on the next network admin load).
    update_site_option(MIGRATE_PENDING_KEY, [
        'time' => time(),
        'user' => get_current_user_id(),
        'reason' => 'network-activate',
    ]);
}

/**
 * Run the actual per-site migration (Network Admin only).
 *
 * - Runs only if pending is set and not done.
 * - Hard-aborts if RRZE-Answers is network-active.
 * - Uses silent activation to avoid redirects/exits during programmatic activation.
 * - Persists report in a transient for display after redirects.
 */
function rrze_answers_migrate_multisite(): void
{
    // Always show potential reports.
    add_action('network_admin_notices', __NAMESPACE__ . '\rrze_answers_migrate_multisite_notice');

    if (get_site_option(MIGRATE_DONE_KEY)) {
        return;
    }

    if (!is_multisite() || !is_network_admin() || !current_user_can('manage_network_plugins')) {
        return;
    }

    $pending = get_site_option(MIGRATE_PENDING_KEY);
    if (empty($pending)) {
        return; // Not scheduled.
    }

    // Ensure plugin APIs exist.
    rrze_answers_ensure_plugin_functions();

    // Safety: never migrate while RRZE-Answers is network-active.
    if (is_plugin_active_for_network(RRZE_ANSWERS_PLUGIN)) {
        // Try once to correct it.
        deactivate_plugins(RRZE_ANSWERS_PLUGIN, false, true);
        rrze_answers_refresh_plugin_caches();

        if (is_plugin_active_for_network(RRZE_ANSWERS_PLUGIN)) {
            set_site_transient(
                MIGRATE_REPORT_KEY,
                [
                    'type' => 'error',
                    'title' => 'RRZE-Answers',
                    'intro' => __('Migration aborted: RRZE-Answers is still network-activated.', 'rrze-answers'),
                    'items' => [],
                    'footer' => __('No site changes were made. Please deactivate RRZE-Answers network-wide and reload this page.', 'rrze-answers'),
                ],
                10 * MINUTE_IN_SECONDS
            );
            return; // Keep pending; allow retry after manual fix.
        }
    }

    $targets = rrze_answers_migrate_targets();
    $report_items = [];
    $changed_sites = 0;

    foreach (get_sites(['number' => 0]) as $site) {
        $blog_id = (int) $site->blog_id;

        switch_to_blog($blog_id);

        try {
            // Check if any old plugin is active on this site.
            $has_target = false;
            foreach ($targets as $p) {
                if (is_plugin_active($p)) {
                    $has_target = true;
                    break;
                }
            }

            if (!$has_target) {
                continue;
            }

            $changed_sites++;

            // Deactivate old plugins.
            $deactivated = [];
            foreach ($targets as $p) {
                if (is_plugin_active($p)) {
                    deactivate_plugins($p, false, false);
                    $deactivated[] = dirname($p);
                }
            }

            // Activate RRZE-Answers on this site only.
            $activated_now = false;
            $activation_error = '';

            if (!is_plugin_active(RRZE_ANSWERS_PLUGIN)) {
                // IMPORTANT: silent=true to avoid redirects/exits.
                $res = activate_plugin(RRZE_ANSWERS_PLUGIN, '', false, true);

                if (is_wp_error($res)) {
                    $activation_error = $res->get_error_message();
                } else {
                    $activated_now = true;
                }
            }

            // Report line.
            $label = get_bloginfo('name') . ' (' . home_url() . ')';

            $parts = [];
            $parts[] = !empty($deactivated)
                ? sprintf('%s %s.', esc_html__('Deactivated:', 'rrze-answers'), esc_html(implode(', ', $deactivated)))
                : esc_html__('Deactivated: none.', 'rrze-answers');

            if ($activation_error !== '') {
                $parts[] = sprintf(
                    '<strong style="color:#b32d2e">%s</strong> %s',
                    esc_html__('RRZE-Answers activation failed:', 'rrze-answers'),
                    esc_html($activation_error)
                );
            } else {
                $parts[] = $activated_now
                    ? esc_html__('RRZE-Answers activated.', 'rrze-answers')
                    : esc_html__('RRZE-Answers already active (no change).', 'rrze-answers');
            }

            $report_items[] = '<strong>' . esc_html($label) . '</strong>: ' . implode(' ', $parts);
        } finally {
            restore_current_blog();
        }
    }

    // Final safety: ensure RRZE-Answers did not become network-active again.
    if (is_plugin_active_for_network(RRZE_ANSWERS_PLUGIN)) {
        deactivate_plugins(RRZE_ANSWERS_PLUGIN, false, true);
        rrze_answers_refresh_plugin_caches();
    }

    if (is_plugin_active_for_network(RRZE_ANSWERS_PLUGIN)) {
        set_site_transient(
            MIGRATE_REPORT_KEY,
            [
                'type' => 'error',
                'title' => 'RRZE-Answers',
                'intro' => __('Migration incomplete: RRZE-Answers could not be kept from being network-activated.', 'rrze-answers'),
                'items' => $report_items,
                'footer' => __('The migration was NOT marked as done. Please fix network activation and reload to retry.', 'rrze-answers'),
            ],
            10 * MINUTE_IN_SECONDS
        );
        return; // Keep pending; allow retry.
    }

    // Success/info report.
    if (!empty($report_items)) {
        set_site_transient(
            MIGRATE_REPORT_KEY,
            [
                'type' => 'success',
                'title' => 'RRZE-Answers',
                'intro' => __('Migration result (old plugins deactivated, RRZE-Answers activated where needed):', 'rrze-answers'),
                'items' => $report_items,
                'footer' => '',
            ],
            10 * MINUTE_IN_SECONDS
        );
    } else {
        set_site_transient(
            MIGRATE_REPORT_KEY,
            [
                'type' => 'info',
                'title' => 'RRZE-Answers',
                'intro' => __('No sites required changes.', 'rrze-answers'),
                'items' => [],
                'footer' => '',
            ],
            10 * MINUTE_IN_SECONDS
        );
    }

    // Mark done and clear pending only on success.
    update_site_option(MIGRATE_DONE_KEY, 1);
    delete_site_option(MIGRATE_PENDING_KEY);
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
    add_action('init', __NAMESPACE__ . '\rrze_migrate_domains');
    register_activation_hook(__FILE__, __NAMESPACE__ . '\rrze_answers_on_activate');
    add_action('admin_init', __NAMESPACE__ . '\rrze_answers_migrate_multisite');
    add_action('network_admin_notices', __NAMESPACE__ . '\rrze_answers_migrate_multisite_notice');

}
