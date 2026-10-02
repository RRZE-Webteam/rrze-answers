<?php

declare(strict_types=1);

// Each phase runs in a fresh process: no old classes, hooks or caches can leak.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__, 2);
$reference = 'fe1c7a8f8a913778feb3ef4960880ffe6c0a2170'; // v1.4.6
$temporary = sys_get_temp_dir() . '/rrze-answers-upgrade-' . bin2hex(random_bytes(6));
mkdir($temporary . '/legacy', 0700, true);

function runUpgradeCommand(array $command, string $directory): void
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $directory);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Upgrade check failed: ' . implode(' ', $command));
    }
}

try {
    $archive = $temporary . '/legacy.tar';
    runUpgradeCommand(['git', 'archive', '--format=tar', '--output=' . $archive, $reference], $root);
    (new PharData($archive))->extractTo($temporary . '/legacy');

    // The same guarded test configuration is used by installation and all requests.
    require $root . '/vendor/autoload.php';
    require __DIR__ . '/wp-tests-config.php';
    $library = getenv('WP_TESTS_DIR') ?: getenv('WP_PHPUNIT__DIR');
    if (!$library || !is_readable($library . '/includes/install.php')) {
        throw new RuntimeException('The WordPress PHPUnit library is missing. Run composer install.');
    }
    // Remove tables left by an interrupted run, including secondary sites.
    // The validated test DB and fixed table prefix cannot address the live site.
    define('WP_DEBUG_DISPLAY', true);
    require ABSPATH . 'wp-includes/plugin.php';
    require ABSPATH . 'wp-includes/class-wpdb.php';
    $database = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $tables = $database->get_col($database->prepare('SHOW TABLES LIKE %s', $database->esc_like($table_prefix) . '%'));
    foreach ($tables as $table) {
        if (!preg_match('/^rrze_upgrade_tests_[A-Za-z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected upgrade table name.');
        }
        $database->query('DROP TABLE `' . $table . '`');
    }
    $database->close();
    runUpgradeCommand([PHP_BINARY, $library . '/includes/install.php', __DIR__ . '/wp-tests-config.php', 'run_ms_tests', 'no_core_tests'], $root);

    $worker = $root . '/tests/upgrade/request.php';
    runUpgradeCommand([PHP_BINARY, $worker, 'seed', $temporary], $root);
    runUpgradeCommand([PHP_BINARY, $worker, 'verify', $temporary], $root);
    runUpgradeCommand([PHP_BINARY, $worker, 'verify', $temporary, '/upgrade-second/'], $root);

    putenv('RRZE_UPGRADE_OUTPUT=' . $temporary . '/editor-content.json');
    runUpgradeCommand(['npm', 'run', 'test:unit', '--', '--runInBand', '--watch=false', 'tests/js/upgrade-fixture.test.js'], $root);
    runUpgradeCommand([PHP_BINARY, $worker, 'save', $temporary], $root);
    runUpgradeCommand([PHP_BINARY, $worker, 'sync', $temporary], $root);
    runUpgradeCommand([PHP_BINARY, $worker, 'verify', $temporary], $root);
    runUpgradeCommand([PHP_BINARY, $worker, 'verify', $temporary, '/upgrade-second/'], $root);
    echo "Upgrade v1.4.6 → current: startup, editor saves and first synchronization passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\nDiagnostic files: " . $temporary . "\n");
    exit(1);
}

// Only remove the private directory created by this invocation, after success.
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($temporary);
