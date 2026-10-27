<?php

declare(strict_types=1);

/**
 * Seed disposable browser fixtures through wp eval-file after plugin activation.
 *
 * This script refuses every database/prefix except the dedicated rollout setup.
 * It creates synthetic public content and never reads the ordinary site's data.
 */
global $wpdb;
if (DB_NAME !== 'rrze_answers_tests' || $wpdb->prefix !== 'rrze_rollout_browser_') {
    throw new RuntimeException('Refusing to seed outside the isolated rollout browser fixture.');
}

wp_set_current_user(1);
$ids = [];
foreach ([
    'faq' => ['Application question', '<p>Application answer: enroll locally.</p>'],
    'glossary' => ['Campus', '<p>Campus definition: local fixture.</p>'],
    'synonym' => ['RRZE', ''],
    'placeholder' => ['Office hours', '&lt;p&gt;Office hours: &lt;strong&gt;09:00–12:00&lt;/strong&gt;&lt;/p&gt;'],
] as $type => [$title, $content]) {
    $ids[$type] = wp_insert_post([
        'post_type' => 'rrze_' . $type,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_content' => $content,
        'meta_input' => ['source' => 'website', 'lang' => 'de', 'anchorfield' => 'rollout-' . $type],
    ], true);
    if (is_wp_error($ids[$type])) {
        throw new RuntimeException($ids[$type]->get_error_message());
    }
    if (in_array($type, ['faq', 'glossary'], true)) {
        wp_set_object_terms($ids[$type], ['Admissions'], 'rrze_' . $type . '_category');
        wp_set_object_terms($ids[$type], ['Campus life'], 'rrze_' . $type . '_tag');
        wp_insert_post([
            'post_type' => 'rrze_' . $type,
            'post_status' => 'publish',
            'post_title' => 'Unselected ' . $type,
            'post_content' => '<p>Must not appear in explicit selection.</p>',
            'meta_input' => ['source' => 'website', 'lang' => 'de'],
        ]);
    }
}
update_post_meta($ids['synonym'], 'synonym', 'Regional Computing Centre');
update_post_meta($ids['synonym'], 'titleLang', 'en');

$blocks = '';
foreach ($ids as $type => $id) {
    $blocks .= '<!-- wp:rrze-answers/' . $type . ' ' . wp_json_encode(['id' => (string) $id]) . " /-->\n\n";
}
$blocks .= '<!-- wp:rrze-answers/faq-widget {"hide_title":1} /-->';
$blocks .= "\n" . '<!-- wp:paragraph --><p><abbr class="rrze-syn" title="Regional Computing Centre" lang="en" data-pron="ar-tset">RRZE</abbr> '
    . '<placeholder class="rrze-placeholder" data-placeholder-id="' . $ids['placeholder']
    . '" data-placeholder-title="Office hours" title="Office hours: &lt;strong&gt;09:00–12:00&lt;/strong&gt;">Office hours</placeholder> '
    . 'Text for inline editing.</p><!-- /wp:paragraph -->';

$pages = [
    'Legacy blocks and inline formats' => $blocks,
    'Shortcodes closed' => '[faq id="' . $ids['faq'] . '" search="1"] [glossary id="' . $ids['glossary'] . '"]',
    'Shortcodes open' => '[faq id="' . $ids['faq'] . '" load_open="1" search="1"]',
    'Navigation and tabs' => '[faq glossarystyle="a-z"] [faq glossary="category" glossarystyle="tabs"] [glossary registerstyle="a-z"]',
];
$pageIds = [];
foreach ($pages as $title => $content) {
    $id = wp_insert_post([
        'post_type' => 'page', 'post_status' => 'publish',
        'post_title' => $title, 'post_content' => wp_slash($content),
    ], true);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    $pageIds[$title] = $id;
}
update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules(false);
echo wp_json_encode(['entries' => $ids, 'pages' => $pageIds], JSON_PRETTY_PRINT) . "\n";
