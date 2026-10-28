<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use WP_REST_Request;
use WP_UnitTestCase;

/** Verify the public export switch at the real REST authentication boundary. */
final class RolloutRESTAccessTest extends WP_UnitTestCase
{
    /**
     * Check anonymous export with its per-content-type setting enabled or off.
     *
     * rest_do_request() alone bypasses authentication. The explicit server
     * authentication check below follows serve_request() without sending HTTP
     * headers. Process isolation keeps REST_REQUEST out of other test cases.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider exportCases
     * @param string $type Answers REST base.
     * @param bool $enabled Whether anonymous export is enabled.
     */
    public function testAnonymousExportHonorsItsSetting(string $type, bool $enabled): void
    {
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }
        self::assertTrue(REST_REQUEST);
        $postId = self::factory()->post->create([
            'post_type' => 'rrze_' . $type,
            'post_status' => 'publish',
            'post_title' => 'Public rollout export',
            'post_content' => '<p>Exported content</p>',
        ]);
        delete_option('rrze-answers');
        add_option('rrze-answers', ['api_active_rrze_' . $type => $enabled ? '1' : '']);
        wp_set_current_user(0);
        $route = '/wp/v2/' . $type;
        $GLOBALS['wp']->query_vars['rest_route'] = $route;
        $server = rest_get_server();
        $authentication = $server->check_authentication();

        if (!$enabled) {
            self::assertWPError($authentication);
            self::assertSame('forbidden', $authentication->get_error_code());
            self::assertSame(403, $authentication->get_error_data()['status']);
            return;
        }

        self::assertNotWPError($authentication);
        $request = new WP_REST_Request('GET', $route);
        $request->set_param('include', [$postId]);
        $response = $server->dispatch($request);
        self::assertSame(200, $response->get_status());
        self::assertSame([$postId], array_column($response->get_data(), 'id'));
    }

    /**
     * Exercise both public synchronized collections with each export state.
     *
     * @return array<string, array{string, bool}>
     */
    public static function exportCases(): array
    {
        return [
            'FAQ enabled' => ['faq', true],
            'FAQ disabled' => ['faq', false],
            'Glossary enabled' => ['glossary', true],
            'Glossary disabled' => ['glossary', false],
        ];
    }
}
