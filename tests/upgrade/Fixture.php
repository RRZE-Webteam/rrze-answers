<?php

declare(strict_types=1);

namespace RRZE\Answers\Upgrade;

/** Synthetic content created by the unmodified v1.4.6 plugin. */
final class Fixture
{
    public static int $assertions = 0;

    public static function check(bool $condition, string $message): void
    {
        self::$assertions++;
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public static function seed(array $blocks): array
    {
        $editor = wp_insert_user(['user_login' => 'upgrade-editor', 'user_pass' => wp_generate_password(), 'role' => 'editor']);
        self::check(!is_wp_error($editor), 'Could not create the fixture editor.');
        $secondSite = wpmu_create_blog('example.org', '/upgrade-second/', 'Upgrade second site', 1);
        self::check(!is_wp_error($secondSite), 'Could not create the second fixture site.');
        $sites = [get_current_blog_id(), (int) $secondSite];
        foreach ($sites as $site) {
            switch_to_blog($site);
            try {
                // Initialize an empty site through v1.4.6's actual one-time migrations.
                foreach (['rrze_update_glossary_cpt', 'rrze_update_synonym_cpt', 'rrze_migrate_domains', 'rrze_migrate_blocks', 'rrze_update_placeholder_cpt'] as $migration) {
                    call_user_func('RRZE\\Answers\\' . $migration);
                }
                add_user_to_blog($site, (int) $editor, 'editor');
                wp_set_current_user(1);
                self::seedSite($site, $blocks);
            } finally {
                restore_current_blog();
            }
        }
        return ['sites' => $sites, 'editor' => (int) $editor, 'snapshot' => self::snapshot($sites)];
    }

    private static function seedSite(int $site, array $blocks): void
    {
        $entries = [
            2001 => ['faq', 'Application question', '<p>Application answer</p>'],
            2002 => ['faq', 'Imported question', '<p>Imported answer</p>'],
            2003 => ['faq', 'Unpublished question', '<p>Unpublished answer</p>'],
            2101 => ['glossary', 'Campus', '<p>Campus definition</p>'],
            2102 => ['glossary', 'Imported term', '<p>Imported definition</p>'],
            2201 => ['synonym', 'RRZE', ''],
            2301 => ['placeholder', 'Opening hours', '&lt;p&gt;Office hours: &lt;strong&gt;09:00–12:00&lt;/strong&gt;&lt;/p&gt;'],
        ];
        foreach ($entries as $id => [$type, $title, $content]) {
            $postId = wp_insert_post([
                'import_id' => $id,
                'post_type' => 'rrze_' . $type,
                'post_status' => $id === 2003 ? 'draft' : 'publish',
                'post_title' => $title . ' site ' . $site,
                'post_content' => $content,
                'post_author' => 1,
            ], true);
            self::check($postId === $id, 'Could not create fixture entry ' . $id);
            $imported = in_array($id, [2002, 2102], true);
            foreach ([
                'source' => $imported ? 'remote' : 'website',
                'lang' => 'de',
                'remoteID' => $imported ? 77 : $id,
                'remoteChanged' => 1700000000,
                'sortfield' => '010',
                'anchorfield' => 'legacy-' . $id,
            ] as $key => $value) {
                update_post_meta($id, $key, $value);
            }
        }
        update_post_meta(2201, 'synonym', 'Regional Computing Centre');
        update_post_meta(2201, 'titleLang', 'en');

        foreach (['faq' => ['enrollment', 'admission'], 'glossary' => ['university', 'studies']] as $type => $slugs) {
            foreach (['category', 'tag'] as $index => $kind) {
                $taxonomy = 'rrze_' . $type . '_' . $kind;
                $term = wp_insert_term(ucfirst($slugs[$index]), $taxonomy, ['slug' => $slugs[$index]]);
                self::check(!is_wp_error($term), 'Could not create fixture taxonomy.');
                $localId = $type === 'faq' ? 2001 : 2101;
                wp_set_object_terms($localId, [(int) $term['term_id']], $taxonomy);
                if ($kind === 'category') {
                    update_term_meta($term['term_id'], 'linked_page', 3001);
                }
                $remote = wp_insert_term('Imported ' . $slugs[$index], $taxonomy);
                self::check(!is_wp_error($remote), 'Could not create imported taxonomy.');
                update_term_meta($remote['term_id'], 'source', 'remote');
                wp_set_object_terms($localId + 1, [(int) $remote['term_id']], $taxonomy);
            }
        }

        foreach (self::pages($blocks) as $index => $page) {
            $id = 3001 + $index;
            self::check(wp_insert_post([
                'import_id' => $id,
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $page['name'],
                'post_content' => wp_slash($page['content']),
                'post_author' => 1,
            ], true) === $id, 'Could not create fixture page ' . $page['name']);
        }

        delete_option('rrze-answers');
        add_option('rrze-answers', [
            'registeredDomains' => ['remote' => 'https://source.example.org/site-' . $site],
            'faq_categories_remote' => ['enrollment'],
            'glossary_categories_remote' => ['university'],
            'frequency' => 'daily',
            'custom_faq_slug' => 'questions-' . $site,
            'custom_glossary_slug' => 'terms-' . $site,
            'redirect_archivpage_uri_synonym' => 3001,
        ]);
        update_option('active_plugins', ['rrze-answers/rrze-answers.php']);
        wp_schedule_event(2000000000, 'daily', 'rrze_answers_auto_sync');
    }

    public static function pages(array $blocks): array
    {
        return array_merge($blocks, [
            [
                'name' => 'mixed-blocks',
                'content' => implode("\n\n", array_column($blocks, 'content')),
                'contains' => ['Application answer', 'Campus definition', 'Regional Computing Centre', 'Office hours'],
            ],
            [
                'name' => 'classic-shortcodes',
                'content' => '[faq id="2001"] [fau_glossar id="2101"] [fau_abbr id="2201"] [placeholder id="2301"]',
                'contains' => ['Application answer', 'Campus definition', 'Regional Computing Centre', 'Office hours'],
            ],
            [
                'name' => 'inline-formats',
                'content' => '<p><abbr class="rrze-synonym" title="Regional Computing Centre" lang="en">RRZE</abbr>: <placeholder class="rrze-placeholder" data-placeholder-id="2301" data-placeholder-title="Opening hours" title="Office hours: &lt;strong&gt;09:00–12:00&lt;/strong&gt;">Opening hours</placeholder></p>',
                'contains' => ['Regional Computing Centre', 'Office hours', '<strong>09:00–12:00</strong>'],
            ],
        ]);
    }

    /** Raw persisted state: rendering and plugin startup must not rewrite it. */
    public static function snapshot(array $sites): array
    {
        global $wpdb;
        $snapshot = [];
        foreach ($sites as $site) {
            switch_to_blog($site);
            try {
                $snapshot[$site] = [
                    'posts' => $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type IN ('rrze_faq','rrze_glossary','rrze_synonym','rrze_placeholder','page') ORDER BY ID", ARRAY_A),
                    'postmeta' => $wpdb->get_results("SELECT m.* FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type IN ('rrze_faq','rrze_glossary','rrze_synonym','rrze_placeholder','page') ORDER BY m.meta_id", ARRAY_A),
                    'terms' => $wpdb->get_results("SELECT * FROM {$wpdb->terms} ORDER BY term_id", ARRAY_A),
                    'term_taxonomy' => $wpdb->get_results("SELECT * FROM {$wpdb->term_taxonomy} ORDER BY term_taxonomy_id", ARRAY_A),
                    'termmeta' => $wpdb->get_results("SELECT * FROM {$wpdb->termmeta} ORDER BY meta_id", ARRAY_A),
                    'relationships' => $wpdb->get_results("SELECT * FROM {$wpdb->term_relationships} ORDER BY object_id, term_taxonomy_id", ARRAY_A),
                    'options' => $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name = 'active_plugins' ORDER BY option_name", $wpdb->esc_like('rrze') . '%'), ARRAY_A),
                    'cron' => (array) wp_get_scheduled_event('rrze_answers_auto_sync'),
                ];
            } finally {
                restore_current_blog();
            }
        }
        $snapshot['network'] = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key = 'active_sitewide_plugins' ORDER BY meta_id", $wpdb->esc_like('rrze') . '%'), ARRAY_A);
        return $snapshot;
    }

    public static function assertSnapshot(array $expected, array $actual, string $phase): void
    {
        foreach ($expected as $site => $tables) {
            if ($site === 'network') {
                self::check($tables === ($actual[$site] ?? null), $phase . ': network options changed');
                continue;
            }
            foreach ($tables as $table => $rows) {
                self::check($rows === ($actual[$site][$table] ?? null), $phase . ': changed ' . $site . '/' . $table);
            }
        }
    }

    public static function renderPages(array $sites, array $blocks): array
    {
        $result = [];
        foreach ($sites as $site) {
            switch_to_blog($site);
            try {
                foreach (self::pages($blocks) as $index => $page) {
                    $GLOBALS['post'] = get_post(3001 + $index);
                    $output = apply_filters('the_content', $GLOBALS['post']->post_content);
                    foreach ($page['contains'] as $text) {
                        self::check(str_contains($output, $text), $site . '/' . $page['name'] . ': missing ' . $text);
                    }
                    self::check(!str_contains($output, 'Unpublished answer'), 'Draft leaked into ' . $page['name']);
                    self::check(!str_contains($output, 'Imported answer') && !str_contains($output, 'Imported definition'), 'Selection broadened in ' . $page['name']);
                    $result[$site][$page['name']] = $output;
                }
            } finally {
                unset($GLOBALS['post']);
                restore_current_blog();
            }
        }
        return $result;
    }

    public static function normalizeOutput(string $output): string
    {
        $output = preg_replace('/header-(?:noid|\d+)-\d+/', 'header-stable', $output);
        $output = preg_replace('/rrze-answers-index-\d+/', 'index-stable', $output);
        // Tools emits the FAQPage scope only once per request.
        return str_replace(' itemscope itemtype="https://schema.org/FAQPage"', '', $output);
    }

    public static function synchronizeImports(array $sites): void
    {
        foreach ($sites as $site) {
            switch_to_blog($site);
            try {
                $api = new \RRZE\Answers\Common\API\SyncAPI();
                $sourceUrl = 'https://source.example.org/site-' . $site;
                self::check($api->getDomains() === ['remote' => $sourceUrl], 'Legacy source settings changed.');
                foreach (['faq' => ['enrollment', 'admission', 2002], 'glossary' => ['university', 'studies', 2102]] as $type => [$category, $tag, $id]) {
                    $mock = static function ($response, $arguments, $url) use ($sourceUrl, $type, $category, $tag) {
                        if (!str_starts_with($url, $sourceUrl . '/wp-json/wp/v2/' . $type . '?')) {
                            return $response;
                        }
                        return [
                            'response' => ['code' => 200],
                            'headers' => ['x-wp-totalpages' => '1'],
                            'body' => wp_json_encode([[
                                'id' => 77, 'remoteID' => 77, 'remoteChanged' => 1700000010,
                                'source' => 'website', 'lang' => 'de',
                                'title' => ['rendered' => 'Updated imported ' . $type],
                                'content' => ['rendered' => '<p>Updated remote content</p>'],
                                'rrze_' . $type . '_category' => ['Imported ' . $category],
                                'rrze_' . $type . '_tag' => ['Imported ' . $tag],
                            ]]),
                        ];
                    };
                    add_filter('pre_http_request', $mock, 9, 3);
                    try {
                        $result = $api->setEntries($type, 'remote', $category, $sourceUrl);
                        self::check(!is_wp_error($result), 'First synchronization failed: ' . (is_wp_error($result) ? $result->get_error_message() : ''));
                        self::check($result['iUpdated'] === 1 && $result['iNew'] === 0 && $result['iDeleted'] === 0, 'Legacy import was not updated in place.');
                        self::check(get_post_field('post_content', $id) === '<p>Updated remote content</p>', 'Imported local ID changed.');
                        self::check(get_post_meta($id, 'source', true) === 'remote' && (int) get_post_meta($id, 'remoteID', true) === 77, 'Imported identity changed.');
                        self::check(get_post_meta($id, 'anchorfield', true) === 'legacy-' . $id, 'Synchronization lost the existing anchor.');
                        $repeat = $api->setEntries($type, 'remote', $category, $sourceUrl);
                        self::check(!is_wp_error($repeat) && $repeat['iUpdated'] === 0 && $repeat['iNew'] === 0 && $repeat['iDeleted'] === 0, 'Repeating synchronization duplicated or changed an import.');
                    } finally {
                        remove_filter('pre_http_request', $mock, 9);
                    }
                }
            } finally {
                restore_current_blog();
            }
        }
    }
}
