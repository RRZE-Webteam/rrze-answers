<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Tools;
use WP_UnitTestCase;

final class IndexNavigationTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        wp_dequeue_script('rrze-answers-tabs');
        parent::tearDown();
    }

    public function testAlphabeticalNavigationRendersForOneLetterWithUniquePrefix(): void
    {
        $output = Tools::createAZ(['A' => true], 'answers-index');

        self::assertStringContainsString('class="letters list-icons"', $output);
        self::assertStringContainsString('href="#answers-index-A"', $output);
        self::assertStringContainsString('<li class="filled">', $output);
    }

    public function testTabsRenderAccessibleControlsAndPanelIdentifiersForOneTerm(): void
    {
        $terms = [
            'General' => ['ID' => 12, 'letter' => 'G'],
        ];

        $output = Tools::createTabs($terms);

        self::assertStringContainsString('role="tablist"', $output);
        self::assertStringContainsString('role="tab"', $output);
        self::assertStringContainsString('aria-selected="true"', $output);
        self::assertStringContainsString('aria-controls="' . $terms['General']['panel_id'] . '"', $output);
        self::assertStringStartsWith('rrze-answers-tabs-', $terms['General']['panel_id']);
        self::assertNotSame($terms['General']['tab_id'], $terms['General']['panel_id']);
    }

    public function testTagcloudRendersDirectlyStyledLinksForOneTerm(): void
    {
        $terms = [
            'General' => ['ID' => 12, 'letter' => 'G'],
        ];

        $output = Tools::createTagcloud($terms, [12 => [100]], 'answers-term');

        self::assertStringContainsString('class="rrze-answers-tagcloud"', $output);
        self::assertStringContainsString('href="#answers-term-12"', $output);
        self::assertStringContainsString('class="rrze-answers-tagcloud-item"', $output);
        self::assertStringContainsString('--rrze-answers-tagcloud-size:22px', $output);
    }

    /** @dataProvider explicitShortcodeProvider */
    public function testAlphabeticalNavigationAppliesToExplicitEntries(
        string $postType,
        string $shortcode,
        string $styleAttribute
    ): void {
        $postId = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Alpha entry',
            'post_content' => 'Definition',
        ]);

        $output = do_shortcode(
            sprintf('[%s id="%d" %s="a-z"]', $shortcode, $postId, $styleAttribute)
        );

        self::assertStringContainsString('class="letters list-icons"', $output);
        self::assertMatchesRegularExpression('/href="#rrze-answers-index-\d+-A"/', $output);
        self::assertMatchesRegularExpression('/<h2 id="rrze-answers-index-\d+-A">A<\/h2>/', $output);
    }

    /** @return array<string, array{string, string, string}> */
    public function explicitShortcodeProvider(): array
    {
        return [
            'FAQ' => ['rrze_faq', 'faq', 'glossary'],
            'Glossary' => ['rrze_glossary', 'glossary', 'register'],
        ];
    }

    /** @dataProvider groupedShortcodeProvider */
    public function testGroupedTabsRenderPanelsAndEnqueueBehavior(
        string $postType,
        string $taxonomy,
        string $shortcode,
        string $groupAttribute
    ): void {
        $firstTerm = wp_insert_term('Alpha group', $taxonomy);
        $secondTerm = wp_insert_term('Beta group', $taxonomy);

        self::assertIsArray($firstTerm);
        self::assertIsArray($secondTerm);

        $firstPost = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Alpha entry',
            'post_content' => 'First definition',
        ]);
        $secondPost = self::factory()->post->create([
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => 'Beta entry',
            'post_content' => 'Second definition',
        ]);

        wp_set_object_terms($firstPost, [(int) $firstTerm['term_id']], $taxonomy);
        wp_set_object_terms($secondPost, [(int) $secondTerm['term_id']], $taxonomy);

        $output = do_shortcode(
            sprintf('[%s %s="category tabs"]', $shortcode, $groupAttribute)
        );

        self::assertStringContainsString('class="rrze-answers-tabs"', $output);
        self::assertSame(2, substr_count($output, 'role="tab"'));
        self::assertSame(2, substr_count($output, 'role="tabpanel"'));
        self::assertSame(1, substr_count($output, ' hidden'));
        self::assertTrue(wp_script_is('rrze-answers-tabs', 'enqueued'));
    }

    /** @return array<string, array{string, string, string, string}> */
    public function groupedShortcodeProvider(): array
    {
        return [
            'FAQ' => ['rrze_faq', 'rrze_faq_category', 'faq', 'glossary'],
            'Glossary' => ['rrze_glossary', 'rrze_glossary_category', 'glossary', 'register'],
        ];
    }
}
