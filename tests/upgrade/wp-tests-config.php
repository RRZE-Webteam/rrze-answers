<?php

declare(strict_types=1);

// Reuse the database safety checks and credentials, with isolated upgrade tables.
require dirname(__DIR__) . '/phpunit/wp-tests-config.php';
$table_prefix = 'rrze_upgrade_tests_';
