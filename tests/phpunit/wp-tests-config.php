<?php

declare(strict_types=1);

/**
 * PHPUnit configuration for the WordPress core test suite.
 *
 * The suite destroys and recreates its tables. It therefore deliberately uses
 * a separate database whose name must contain "test".
 */

function rrze_answers_test_config_constant(string $configFile, string $name): ?string
{
    if (!is_readable($configFile)) {
        return null;
    }

    $contents = file_get_contents($configFile);

    if ($contents === false) {
        return null;
    }

    $constant = preg_quote($name, '/');
    $pattern = '/^[ \t]*define\s*\(\s*([\'\"])' . $constant . '\1\s*,\s*([\'\"])(.*?)\2\s*\)\s*;/ms';

    if (!preg_match($pattern, $contents, $matches)) {
        return null;
    }

    return stripcslashes($matches[3]);
}

function rrze_answers_test_config_value(
    string $environmentName,
    string $constantName,
    string $sourceConfig,
    string $fallback = ''
): string {
    $environmentValue = getenv($environmentName);

    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    return rrze_answers_test_config_constant($sourceConfig, $constantName) ?? $fallback;
}

$coreDirectory = getenv('WP_CORE_DIR');

if ($coreDirectory === false || $coreDirectory === '') {
    $coreDirectory = dirname(__DIR__, 5);
}

$coreDirectory = rtrim($coreDirectory, '/\\') . DIRECTORY_SEPARATOR;
$sourceConfig = getenv('WP_TESTS_SOURCE_CONFIG');

if ($sourceConfig === false || $sourceConfig === '') {
    $sourceConfig = $coreDirectory . 'wp-config.php';
}

if (!is_readable($coreDirectory . 'wp-settings.php')) {
    throw new RuntimeException(
        'WordPress core was not found. Set WP_CORE_DIR to a WordPress installation.'
    );
}

$testDatabase = getenv('WP_TESTS_DB_NAME') ?: 'rrze_answers_tests';
$sourceDatabase = rrze_answers_test_config_constant($sourceConfig, 'DB_NAME');

if (!preg_match('/^[A-Za-z0-9_]+$/', $testDatabase)) {
    throw new RuntimeException('WP_TESTS_DB_NAME may only contain letters, numbers and underscores.');
}

if (stripos($testDatabase, 'test') === false) {
    throw new RuntimeException('Refusing to run: the PHPUnit database name must contain "test".');
}

if ($sourceDatabase !== null && $testDatabase === $sourceDatabase) {
    throw new RuntimeException('Refusing to run PHPUnit against the configured WordPress database.');
}

define('DB_NAME', $testDatabase);
define('DB_USER', rrze_answers_test_config_value('WP_TESTS_DB_USER', 'DB_USER', $sourceConfig, 'root'));
define('DB_PASSWORD', rrze_answers_test_config_value('WP_TESTS_DB_PASSWORD', 'DB_PASSWORD', $sourceConfig));
define('DB_HOST', rrze_answers_test_config_value('WP_TESTS_DB_HOST', 'DB_HOST', $sourceConfig, 'localhost'));
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

define('ABSPATH', $coreDirectory);
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'RRZE Answers Tests');
define('WP_PHP_BINARY', PHP_BINARY);
define('WP_DEBUG', true);

$table_prefix = 'wptests_';
