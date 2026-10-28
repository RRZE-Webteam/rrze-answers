<?php

declare(strict_types=1);

/** Bootstrap a separately exported main checkout using the guarded test DB. */
$candidateRoot = dirname(__DIR__, 2);
$mainDirectory = getenv('RRZE_ROLLOUT_MAIN_DIR');
$mainRoot = is_string($mainDirectory) ? realpath($mainDirectory) : false;
if ($mainRoot === false || !is_readable($mainRoot . '/rrze-answers.php')) {
    throw new RuntimeException('Set RRZE_ROLLOUT_MAIN_DIR to a separate git archive of the intended main commit.');
}
if ($mainRoot === realpath($candidateRoot)) {
    throw new RuntimeException('The rollout baseline must use a separate checkout from the candidate.');
}

$loader = require $candidateRoot . '/vendor/autoload.php';
$loader->setPsr4('RRZE\\Answers\\', [$mainRoot . '/includes']);
$wpTestsDir = getenv('WP_TESTS_DIR') ?: getenv('WP_PHPUNIT__DIR');
if (!$wpTestsDir || !is_readable($wpTestsDir . '/includes/functions.php')) {
    throw new RuntimeException('WordPress test dependencies are missing. Run composer install first.');
}
define('WP_TESTS_CONFIG_FILE_PATH', $candidateRoot . '/tests/phpunit/wp-tests-config.php');
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $candidateRoot . '/vendor/yoast/phpunit-polyfills');
require $wpTestsDir . '/includes/functions.php';

/**
 * Exclude only main's known pre-init translation notice from this comparison.
 *
 * This baseline issue otherwise aborts PHPUnit's isolated child processes
 * before their REST or settings assertions run. Other diagnostics remain live.
 *
 * @param bool $trigger Whether WordPress should emit the diagnostic.
 * @param string $function Function reported as incorrectly called.
 * @param string $message Diagnostic text identifying the affected textdomain.
 * @return bool Whether the original diagnostic should still be emitted.
 */
$allowKnownTranslationNotice = static function (bool $trigger, string $function, string $message): bool {
    if ($function === '_load_textdomain_just_in_time' && str_contains($message, 'rrze-answers')) {
        return false;
    }

    return $trigger;
};
tests_add_filter('doing_it_wrong_trigger_error', $allowKnownTranslationNotice, 10, 3);

/** Load only the baseline entrypoint at WordPress's usual plugin phase. */
$loadMain = static function () use ($mainRoot): void {
    require $mainRoot . '/rrze-answers.php';
};
tests_add_filter('muplugins_loaded', $loadMain);
require $wpTestsDir . '/includes/bootstrap.php';

$loadedFile = (new ReflectionClass(RRZE\Answers\Common\API\SyncAPI::class))->getFileName();
if (!is_string($loadedFile) || !str_starts_with($loadedFile, $mainRoot . '/')) {
    throw new RuntimeException('The main comparison unexpectedly loaded candidate plugin classes.');
}
