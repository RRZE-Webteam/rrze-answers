<?php

declare(strict_types=1);

/** Check real HTTP archive responses in the disposable loopback fixture. */
global $wpdb;
$fixtureUrls = [
    'rrze_rollout_browser_' => 'http://127.0.0.1:8876',
    'rrze_rollout_browser70_' => 'http://127.0.0.1:8877',
];
if (DB_NAME !== 'rrze_answers_tests' || !isset($fixtureUrls[$wpdb->prefix]) || home_url() !== $fixtureUrls[$wpdb->prefix]) {
    throw new RuntimeException('Refusing to change settings outside the rollout browser fixture.');
}
$redirectPage = get_page_by_path('legacy-blocks-and-inline-formats');
if (!$redirectPage instanceof WP_Post || $redirectPage->post_status !== 'publish') {
    throw new RuntimeException('The synthetic redirect target must exist before running HTTP checks.');
}

/**
 * Request only this fixture's loopback server without following redirects.
 *
 * @param string $path Local request path, without a host or scheme.
 * @return array{status: int, location: string} Actual status and Location header.
 */
function rolloutLocalResponse(string $path): array
{
    $request = curl_init(home_url('/' . ltrim($path, '/')));
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($request);
    if ($raw === false) {
        throw new RuntimeException(curl_error($request));
    }
    $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    $header = substr($raw, 0, (int) curl_getinfo($request, CURLINFO_HEADER_SIZE));
    preg_match('/^Location:\s*(.+)$/mi', $header, $match);
    return ['status' => $status, 'location' => trim($match[1] ?? '')];
}

$original = get_option('rrze-answers');
file_put_contents(ABSPATH . 'http-original-options.json', wp_json_encode($original));
$results = [];
$failed = false;
try {
    foreach (['faq', 'glossary', 'synonym', 'placeholder'] as $type) {
        foreach (['default', 'redirect', 'custom-redirect', 'missing-redirect', 'missing-entry'] as $case) {
            $options = (array) $original;
            $custom = in_array($case, ['custom-redirect', 'missing-entry'], true);
            $slug = $custom ? 'rollout-' . $type . '-archive' : $type;
            $options['custom_' . $type . '_slug'] = $custom ? $slug : '';
            $options['redirect_archivpage_uri_' . $type] = match ($case) {
                'redirect', 'custom-redirect' => $redirectPage->ID,
                'missing-redirect' => 9999999,
                default => 0,
            };
            // These are persisted-state HTTP checks, not the settings-save path.
            // Replace fixture options without invoking checkSlugChange(), whose
            // repeated init hooks are covered separately by RolloutSlugChangeTest.
            delete_option('rrze-answers');
            add_option('rrze-answers', $options);
            delete_option('rewrite_rules');
            $path = $slug . ($case === 'missing-entry' ? '/missing-rollout-entry/' : '/');
            $expected = [
                'status' => match ($case) {
                    'redirect', 'custom-redirect' => 301,
                    'missing-entry' => 404,
                    default => 200,
                },
                'location' => in_array($case, ['redirect', 'custom-redirect'], true)
                    ? get_permalink($redirectPage) : '',
            ];
            $actual = rolloutLocalResponse($path);
            $passed = $actual === $expected;
            $failed = $failed || !$passed;
            $results[$type . '/' . $case] = compact('path', 'expected', 'actual', 'passed');
        }
    }
} finally {
    delete_option('rrze-answers');
    if ($original !== false) {
        add_option('rrze-answers', $original);
    }
    delete_option('rewrite_rules');
}

echo wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if ($failed) {
    WP_CLI::halt(1);
}
