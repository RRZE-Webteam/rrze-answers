<?php

declare(strict_types=1);

$pluginDir = dirname(__DIR__, 2);
$autoload = $pluginDir . '/vendor/autoload.php';

if (!is_readable($autoload)) {
    fwrite(STDERR, "Composer dependencies are missing. Run `composer install` first.\n");
    exit(1);
}

require_once $autoload;

$wpTestsDir = getenv('WP_TESTS_DIR') ?: getenv('WP_PHPUNIT__DIR');

if (!$wpTestsDir || !is_readable($wpTestsDir . '/includes/functions.php')) {
    fwrite(STDERR, "The WordPress PHPUnit test library could not be found.\n");
    exit(1);
}

if (!defined('WP_TESTS_CONFIG_FILE_PATH')) {
    define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');
}

if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $pluginDir . '/vendor/yoast/phpunit-polyfills');
}

require_once $wpTestsDir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function () use ($pluginDir): void {
        require $pluginDir . '/rrze-answers.php';
    }
);

require $wpTestsDir . '/includes/bootstrap.php';
