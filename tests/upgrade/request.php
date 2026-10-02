<?php

declare(strict_types=1);

use RRZE\Answers\Upgrade\Fixture;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__, 2);
$phase = $argv[1] ?? '';
$temporary = $argv[2] ?? '';
if (!in_array($phase, ['seed', 'verify', 'save', 'sync'], true) || !is_dir($temporary . '/legacy')) {
    throw new RuntimeException('Run this worker through composer test:upgrade.');
}

$pluginRoot = realpath($phase === 'seed' ? $temporary . '/legacy' : $root);
$loader = require $root . '/vendor/autoload.php';
// Composer must not accidentally load current classes during the legacy request.
$loader->setPsr4('RRZE\\Answers\\', [$pluginRoot . '/includes']);
require __DIR__ . '/Fixture.php';
require __DIR__ . '/wp-tests-config.php';
$library = getenv('WP_TESTS_DIR') ?: getenv('WP_PHPUNIT__DIR');
require $library . '/includes/functions.php';

error_reporting(E_ALL & ~E_DEPRECATED);
$coreDeprecations = [];
set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$coreDeprecations): bool {
    // Older WordPress releases emit PHP 8.5 deprecations in their own code.
    // Record those separately; warnings/notices and plugin deprecations still fail.
    if ($severity === E_DEPRECATED && (str_starts_with($file, ABSPATH . 'wp-includes/') || str_starts_with($file, ABSPATH . 'wp-admin/'))) {
        $coreDeprecations[$file . ':' . $line] = $message;
        return true;
    }
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('DISABLE_WP_CRON', true);
define('WP_DEFAULT_THEME', 'default');
tests_reset__SERVER();
$GLOBALS['base'] = '/';
$GLOBALS['PHP_SELF'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'example.org';
$_SERVER['REQUEST_URI'] = $argv[3] ?? '/';
chdir($pluginRoot);

$manifestPath = $temporary . '/manifest.json';
$manifest = $phase === 'seed' ? [] : json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$blocks = json_decode(file_get_contents($root . '/tests/fixtures/v1.4.6/blocks.json'), true, 512, JSON_THROW_ON_ERROR);
$httpRequests = [];

// Load only the selected checkout while retaining the persisted activation state.
tests_add_filter('pre_option_active_plugins', '__return_empty_array');
tests_add_filter('pre_site_option_active_sitewide_plugins', '__return_empty_array');
// v1.4.6 predates the fix for translation loading before init.
tests_add_filter('doing_it_wrong_trigger_error', static function ($trigger, $function) use ($phase) {
    return $phase === 'seed' && $function === '_load_textdomain_just_in_time' ? false : $trigger;
}, 10, 2);
tests_add_filter('pre_http_request', static function ($response, $arguments, $url) use (&$httpRequests) {
    if ($response !== false) {
        return $response;
    }
    $httpRequests[] = $url;
    return new WP_Error('upgrade_test_offline', 'External HTTP is disabled in upgrade tests.');
}, 10, 3);
tests_add_filter('muplugins_loaded', static function () use ($pluginRoot, $phase, $manifest): void {
    if ($phase !== 'seed') {
        Fixture::assertSnapshot($manifest['snapshot'], Fixture::snapshot($manifest['sites']), 'Before plugin load');
    }
    require $pluginRoot . '/rrze-answers.php';
});
tests_add_filter('rrze_answers_accordion_backend', static fn (): string => 'native');

try {
    require ABSPATH . 'wp-settings.php';
    if ($phase === 'seed') {
        echo 'WordPress ' . $GLOBALS['wp_version'] . ' / PHP ' . PHP_VERSION . "\n";
        Fixture::check(RRZE\Answers\plugin()->getVersion() === '1.4.6', 'Wrong legacy plugin loaded.');
        Fixture::check(str_starts_with((new ReflectionClass(RRZE\Answers\Main::class))->getFileName(), $pluginRoot . '/'), 'Composer loaded the wrong plugin classes.');
        $manifest = Fixture::seed($blocks);
        $manifest['legacy_output'] = Fixture::renderPages($manifest['sites'], $blocks);
        Fixture::assertSnapshot($manifest['snapshot'], Fixture::snapshot($manifest['sites']), 'Legacy rendering');
    } else {
        $expectedSite = ($_SERVER['REQUEST_URI'] === '/upgrade-second/') ? $manifest['sites'][1] : $manifest['sites'][0];
        Fixture::check(get_current_blog_id() === $expectedSite, 'The request booted the wrong site.');
        Fixture::check(str_starts_with((new ReflectionClass(RRZE\Answers\Main::class))->getFileName(), $root . '/'), 'Wrong current plugin loaded.');
        Fixture::assertSnapshot($manifest['snapshot'], Fixture::snapshot($manifest['sites']), 'After plugin startup');
        Fixture::check(get_post_type_object('rrze_faq')->rewrite['slug'] === 'questions-' . get_current_blog_id(), 'Existing FAQ slug was not registered.');
        Fixture::check(get_post_type_object('rrze_glossary')->rewrite['slug'] === 'terms-' . get_current_blog_id(), 'Existing glossary slug was not registered.');
        $beforeOutput = Fixture::renderPages($manifest['sites'], $blocks);
        Fixture::assertSnapshot($manifest['snapshot'], Fixture::snapshot($manifest['sites']), 'Current rendering');

        if ($phase === 'save') {
            $migrated = json_decode(file_get_contents($temporary . '/editor-content.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($manifest['sites'] as $site) {
                switch_to_blog($site);
                try {
                    wp_set_current_user($manifest['editor']);
                    foreach (Fixture::pages($blocks) as $index => $fixturePage) {
                        $content = $migrated[$fixturePage['name']] ?? $fixturePage['content'];
                        $request = new WP_REST_Request('POST', '/wp/v2/pages/' . (3001 + $index));
                        $request->set_param('content', $content);
                        $response = rest_do_request($request);
                        Fixture::check($response->get_status() === 200, 'Editor save failed: ' . $fixturePage['name'] . ' ' . wp_json_encode($response->get_data()));
                        Fixture::check(get_post_field('post_content', 3001 + $index) === $content, 'Editor save changed content: ' . $fixturePage['name']);
                    }
                } finally {
                    restore_current_blog();
                }
            }
            wp_set_current_user(0);
            $afterOutput = Fixture::renderPages($manifest['sites'], $blocks);
            foreach ($beforeOutput as $site => $pages) {
                foreach ($pages as $name => $output) {
                    Fixture::check(Fixture::normalizeOutput($output) === Fixture::normalizeOutput($afterOutput[$site][$name]), 'Editor round trip changed frontend output: ' . $site . '/' . $name);
                }
            }
            $after = Fixture::snapshot($manifest['sites']);
            $beforeUnedited = $manifest['snapshot'];
            $afterUnedited = $after;
            foreach ($manifest['sites'] as $site) {
                // Page content, modification dates and editor metadata may change on save.
                foreach (['posts', 'postmeta'] as $table) {
                    $idKey = $table === 'posts' ? 'ID' : 'post_id';
                    $notEdited = static fn (array $row): bool => (int) $row[$idKey] < 3001 || (int) $row[$idKey] >= 3001 + count(Fixture::pages($blocks));
                    $beforeUnedited[$site][$table] = array_values(array_filter($beforeUnedited[$site][$table], $notEdited));
                    $afterUnedited[$site][$table] = array_values(array_filter($afterUnedited[$site][$table], $notEdited));
                }
            }
            Fixture::assertSnapshot($beforeUnedited, $afterUnedited, 'Saving pages changed unrelated data');
            $manifest['snapshot'] = $after;
        }
        if ($phase === 'sync') {
            Fixture::synchronizeImports($manifest['sites']);
            $after = Fixture::snapshot($manifest['sites']);
            $beforeUnedited = $manifest['snapshot'];
            $afterUnedited = $after;
            foreach ($manifest['sites'] as $site) {
                foreach (['posts', 'postmeta'] as $table) {
                    $idKey = $table === 'posts' ? 'ID' : 'post_id';
                    $notImported = static fn (array $row): bool => !in_array((int) $row[$idKey], [2002, 2102], true);
                    $beforeUnedited[$site][$table] = array_values(array_filter($beforeUnedited[$site][$table], $notImported));
                    $afterUnedited[$site][$table] = array_values(array_filter($afterUnedited[$site][$table], $notImported));
                }
            }
            Fixture::assertSnapshot($beforeUnedited, $afterUnedited, 'Synchronization changed unrelated data');
            $manifest['snapshot'] = $after;
        }
    }
    Fixture::check($httpRequests === [], 'Unexpected remote request: ' . implode(', ', $httpRequests));
    $manifest['core_deprecations'][$phase] = $coreDeprecations;
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo $phase . ': ' . Fixture::$assertions . " assertions passed across two sites.\n";
    if ($coreDeprecations) {
        echo count($coreDeprecations) . " WordPress core deprecation locations recorded under PHP " . PHP_VERSION . ".\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    exit(1);
}
