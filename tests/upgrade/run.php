<?php

declare(strict_types=1);

// Each phase runs in a fresh process: no old classes, hooks or caches can leak.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__, 2);
$references = [
    'v1.4.6' => ['commit' => 'fe1c7a8f8a913778feb3ef4960880ffe6c0a2170', 'version' => '1.4.6'],
    'main' => ['commit' => '3b458c812ba61c1f9b8beac22022cfd7b159ba58', 'version' => '1.4.7'],
];
$requestedReference = getenv('RRZE_UPGRADE_REFERENCE') ?: 'v1.4.6';
foreach ($references as $name => $candidate) {
    if ($requestedReference === $candidate['commit']) {
        $requestedReference = $name;
        break;
    }
}
if (!isset($references[$requestedReference])) {
    throw new RuntimeException('RRZE_UPGRADE_REFERENCE must be v1.4.6, main, or one of their pinned commit hashes.');
}
$reference = $references[$requestedReference];
$rollback = getenv('RRZE_UPGRADE_ROLLBACK') === '1';
$syncOnly = getenv('RRZE_UPGRADE_SYNC_ONLY') === '1';
$temporary = sys_get_temp_dir() . '/rrze-answers-upgrade-' . bin2hex(random_bytes(6));
mkdir($temporary . '/legacy', 0700, true);
$results = ['reference' => $requestedReference, 'commit' => $reference['commit'], 'sync_only' => $syncOnly, 'phases' => []];
file_put_contents($temporary . '/run-config.json', json_encode($reference, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

/**
 * Execute a phase and persist its exit status and output before another phase starts.
 *
 * @param list<string> $command Executable and arguments without shell interpolation.
 * @param string $directory Working directory for the child process.
 * @param string $phase Unique diagnostic name for this invocation.
 * @param string $temporary Private directory containing the retained evidence.
 * @param array<string, mixed> $results Accumulated results, including failed rollback phases.
 * @return bool Whether the phase completed successfully.
 */
function runUpgradeCommand(array $command, string $directory, string $phase, string $temporary, array &$results): bool
{
    $logPath = $temporary . '/' . $phase . '.log';
    $process = proc_open($command, [STDIN, ['file', $logPath, 'w'], ['redirect', 1]], $pipes, $directory);
    $exitCode = is_resource($process) ? proc_close($process) : 1;
    $results['phases'][$phase] = ['command' => $command, 'exit_code' => $exitCode, 'passed' => $exitCode === 0, 'log' => $logPath];
    file_put_contents($temporary . '/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo '[' . $phase . '] ' . ($exitCode === 0 ? 'PASS' : 'FAIL') . "\n";
    if (is_readable($logPath)) {
        echo file_get_contents($logPath);
    }
    return $exitCode === 0;
}

try {
    $archive = $temporary . '/legacy.tar';
    if (!runUpgradeCommand(['git', 'archive', '--format=tar', '--output=' . $archive, $reference['commit']], $root, 'archive', $temporary, $results)) {
        throw new RuntimeException('Could not export the pinned legacy plugin.');
    }
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
    if (!runUpgradeCommand([PHP_BINARY, $library . '/includes/install.php', __DIR__ . '/wp-tests-config.php', 'run_ms_tests', 'no_core_tests'], $root, 'install', $temporary, $results)) {
        throw new RuntimeException('Could not initialize the isolated upgrade database.');
    }

    $worker = $root . '/tests/upgrade/request.php';
    $beforeChange = $syncOnly ? 'before-sync' : 'before-save';
    $phases = [
        ['seed', 'seed', '/'],
        ['verify-' . $beforeChange, 'verify', '/'],
        ['verify-' . $beforeChange . '-second-site', 'verify', '/upgrade-second/'],
    ];
    if ($rollback) {
        $phases[] = ['rollback-' . $beforeChange, 'rollback', '/'];
        $phases[] = ['rollback-' . $beforeChange . '-second-site', 'rollback', '/upgrade-second/'];
    }
    if (!$syncOnly) {
        $phases[] = ['editor-serialization', 'editor', '/'];
        $phases[] = ['save', 'save', '/'];
        if ($rollback) {
            $phases[] = ['rollback-after-save', 'rollback', '/'];
            $phases[] = ['rollback-after-save-second-site', 'rollback', '/upgrade-second/'];
        }
    }
    $phases[] = ['sync', 'sync', '/'];
    if ($rollback) {
        $phases[] = ['rollback-after-sync', 'rollback', '/'];
        $phases[] = ['rollback-after-sync-second-site', 'rollback', '/upgrade-second/'];
    }
    $phases[] = ['verify-after-sync', 'verify', '/'];
    $phases[] = ['verify-after-sync-second-site', 'verify', '/upgrade-second/'];

    foreach ($phases as [$label, $phase, $path]) {
        $command = [PHP_BINARY, $worker, $phase, $temporary, $path, $label];
        if ($phase === 'editor') {
            putenv('RRZE_UPGRADE_OUTPUT=' . $temporary . '/editor-content.json');
            $command = ['npm', 'run', 'test:unit', '--', '--runInBand', '--watch=false', 'tests/js/upgrade-fixture.test.js'];
        }
        $passed = runUpgradeCommand($command, $root, $label, $temporary, $results);
        // Rendering failures in an old version are findings, not a reason to skip
        // later checkpoints. Forward failures invalidate dependent phases.
        if (!$passed && $phase !== 'rollback') {
            throw new RuntimeException('Upgrade phase failed: ' . $label);
        }
    }
    $failed = [];
    foreach ($results['phases'] as $label => $result) {
        if (!$result['passed']) {
            $failed[] = $label;
        }
    }
    if ($failed !== []) {
        throw new RuntimeException('Compatibility failures: ' . implode(', ', $failed));
    }
    echo 'Upgrade ' . $requestedReference . ' → current: startup, '
        . ($syncOnly ? '' : 'editor saves and ') . 'first/repeated synchronization passed.' . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\nDiagnostic files: " . $temporary . "\n");
    exit(1);
}

if ($rollback || getenv('RRZE_UPGRADE_KEEP_DIAGNOSTICS') === '1') {
    echo 'Diagnostic files: ' . $temporary . "\n";
    exit(0);
}

// Only remove the private directory created by this invocation, after success.
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($temporary);
