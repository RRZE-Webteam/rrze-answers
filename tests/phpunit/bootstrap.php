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

/**
 * Keep integration tests offline while allowing earlier HTTP fixtures to respond.
 *
 * @param false|array<string, mixed>|WP_Error $preempt An earlier mocked response.
 * @param array<string, mixed> $arguments WordPress HTTP request arguments.
 * @param string $url Requested URL.
 * @return false|array<string, mixed>|WP_Error Existing response, local allowance or transport error.
 */
function rrze_answers_tests_block_external_http($preempt, array $arguments, string $url)
{
    if ($preempt !== false) {
        return $preempt;
    }

    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    if (
        in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)
        || str_ends_with($host, '.localhost')
    ) {
        return false;
    }

    return new WP_Error(
        'rrze_answers_test_external_http_blocked',
        'Unmocked external HTTP is disabled in the local integration suite.'
    );
}

tests_add_filter('pre_http_request', 'rrze_answers_tests_block_external_http', PHP_INT_MAX, 3);

tests_add_filter(
    'muplugins_loaded',
    /**
     * Load Answers before WordPress initialization in the isolated test database.
     *
     * @return void
     */
    static function () use ($pluginDir): void {
        require $pluginDir . '/rrze-answers.php';
    }
);

require $wpTestsDir . '/includes/bootstrap.php';
