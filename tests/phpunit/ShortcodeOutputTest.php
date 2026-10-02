<?php
declare(strict_types=1);

namespace RRZE\Answers\Tests;

use WP_UnitTestCase;

final class ShortcodeOutputTest extends WP_UnitTestCase
{
    /** @dataProvider visibilityCases */
    public function testUnpublishedEntryIsNotExposed(string $type, string $status): void
    {
        $id = self::factory()->post->create([
            'post_type' => 'rrze_' . $type,
            'post_status' => $status,
            'post_title' => 'Review fixture',
            'post_content' => 'REVIEW_PRIVATE_CONTENT',
        ]);
        update_post_meta($id, 'synonym', 'REVIEW_PRIVATE_CONTENT');
        wp_set_current_user(0);
        self::assertStringNotContainsString('REVIEW_PRIVATE_CONTENT', do_shortcode(sprintf('[%s id="%d"]', $type, $id)));
    }

    public function visibilityCases(): array
    {
        $cases = [];
        foreach (['faq', 'glossary', 'placeholder', 'synonym'] as $type) {
            foreach (['draft', 'pending', 'private', 'trash'] as $status) {
                $cases[$type . '-' . $status] = [$type, $status];
            }
        }
        return $cases;
    }

    /** @dataProvider multiSelectionCases */
    public function testMultipleSelectedEntriesRender(string $type, bool $block): void
    {
        $ids = [];
        foreach (['REVIEW_FIRST', 'REVIEW_SECOND'] as $text) {
            $id = self::factory()->post->create([
                'post_type' => 'rrze_' . $type,
                'post_status' => 'publish',
                'post_content' => $text,
            ]);
            update_post_meta($id, 'synonym', $text);
            $ids[] = $id;
        }
        $output = $block
            ? do_blocks('<!-- wp:rrze-answers/' . $type . ' ' . wp_json_encode(['id' => $ids]) . ' /-->')
            : do_shortcode('[' . $type . ' id="' . implode(',', $ids) . '"]');
        self::assertStringContainsString('REVIEW_SECOND', $output);
        self::assertStringContainsString('REVIEW_FIRST', $output);
        self::assertLessThan(strpos($output, 'REVIEW_SECOND'), strpos($output, 'REVIEW_FIRST'));
    }

    public function multiSelectionCases(): array
    {
        return [
            'faq-shortcode' => ['faq', false],
            'faq-block' => ['faq', true],
            'glossary-shortcode' => ['glossary', false],
            'glossary-block' => ['glossary', true],
            'placeholder-shortcode' => ['placeholder', false],
            'placeholder-block' => ['placeholder', true],
            'synonym-shortcode' => ['synonym', false],
            'synonym-block' => ['synonym', true],
        ];
    }

    public function testAbbreviationEscapesAttributeQuotes(): void
    {
        $id = self::factory()->post->create(['post_type' => 'rrze_synonym', 'post_status' => 'publish']);
        $value = sanitize_text_field('Full "quoted" form');
        update_post_meta($id, 'synonym', $value);
        self::assertStringContainsString('title="' . esc_attr($value) . '"', do_shortcode('[fau_abbr id="' . $id . '"]'));
    }

    public function testPlaceholderDoesNotTurnEncodedTextIntoExecutableMarkup(): void
    {
        $id = self::factory()->post->create([
            'post_type' => 'rrze_placeholder',
            'post_status' => 'publish',
            'post_content' => wp_kses_post('&lt;img src=x onerror=alert(1)&gt;'),
        ]);
        self::assertStringNotContainsString('<img src=x onerror=', do_shortcode('[placeholder id="' . $id . '"]'));
    }

    /** @dataProvider typeCases */
    public function testExplicitSelectionRejectsOtherPostTypesAndMissingIds(string $type): void
    {
        $otherId = self::factory()->post->create([
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_content' => 'REVIEW_OTHER_POST',
        ]);
        update_post_meta($otherId, 'synonym', 'REVIEW_OTHER_POST');
        self::assertStringNotContainsString('REVIEW_OTHER_POST', do_shortcode('[' . $type . ' id="' . $otherId . '"]'));
        self::assertStringNotContainsString('REVIEW_OTHER_POST', do_shortcode('[' . $type . ' id="99999999,invalid"]'));
    }

    /** @dataProvider typeCases */
    public function testPasswordProtectedEntriesAreNotEmbeddedByIdOrInLists(string $type): void
    {
        $id = self::factory()->post->create([
            'post_type' => 'rrze_' . $type,
            'post_status' => 'publish',
            'post_password' => 'review-password',
            'post_content' => 'REVIEW_PASSWORD_CONTENT',
        ]);
        update_post_meta($id, 'synonym', 'REVIEW_PASSWORD_CONTENT');
        wp_set_current_user(0);
        self::assertStringNotContainsString('REVIEW_PASSWORD_CONTENT', do_shortcode('[' . $type . ' id="' . $id . '"]'));
        self::assertStringNotContainsString('REVIEW_PASSWORD_CONTENT', do_shortcode('[' . $type . ']'));
    }

    /** @dataProvider typeCases */
    public function testLegacyIdListsSurviveBlockRendering(string $type): void
    {
        $ids = [];
        foreach (['REVIEW_ALPHA', 'REVIEW_BETA'] as $value) {
            $id = self::factory()->post->create([
                'post_type' => 'rrze_' . $type,
                'post_status' => 'publish',
                'post_content' => $value,
            ]);
            update_post_meta($id, 'synonym', $value);
            $ids[] = $id;
        }
        $output = do_blocks('<!-- wp:rrze-answers/' . $type . ' ' . wp_json_encode(['id' => implode(',', $ids)]) . ' /-->');
        self::assertStringContainsString('REVIEW_ALPHA', $output);
        self::assertStringContainsString('REVIEW_BETA', $output);
    }

    public function typeCases(): array
    {
        return array_map(static fn ($type) => [$type], ['faq', 'glossary', 'placeholder', 'synonym']);
    }

    /** @dataProvider simpleTypeCases */
    public function testSlugTakesPrecedenceAndLanguageFiltersSelections(string $type): void
    {
        $first = self::factory()->post->create([
            'post_type' => 'rrze_' . $type, 'post_status' => 'publish', 'post_name' => 'review-first',
            'post_content' => 'REVIEW_FIRST',
        ]);
        $second = self::factory()->post->create([
            'post_type' => 'rrze_' . $type, 'post_status' => 'publish', 'post_name' => 'review-second',
            'post_content' => 'REVIEW_SECOND',
        ]);
        update_post_meta($first, 'lang', 'de');
        update_post_meta($first, 'synonym', 'REVIEW_FIRST');
        update_post_meta($second, 'lang', 'en');
        update_post_meta($second, 'synonym', 'REVIEW_SECOND');
        $output = do_shortcode('[' . $type . ' slug="review-first" id="' . $second . '"]');
        self::assertStringContainsString('REVIEW_FIRST', $output);
        self::assertStringNotContainsString('REVIEW_SECOND', $output);
        self::assertSame('', do_shortcode('[' . $type . ' slug="review-missing" id="' . $first . '"]'));
        self::assertSame('', do_shortcode('[' . $type . ' id="invalid"]'));
        foreach ([' id="' . $first . ',' . $second . '"', ''] as $selection) {
            $output = do_shortcode('[' . $type . $selection . ' lang="en"]');
            self::assertStringContainsString('REVIEW_SECOND', $output);
            self::assertStringNotContainsString('REVIEW_FIRST', $output);
        }
    }

    public function simpleTypeCases(): array
    {
        return [['placeholder'], ['synonym']];
    }

    public function testSynonymTextAndAttributesCannotCreateHtml(): void
    {
        $id = self::factory()->post->create([
            'post_type' => 'rrze_synonym', 'post_status' => 'publish',
            'post_title' => '&lt;img src=x onerror=alert(1)&gt;',
        ]);
        $text = 'Name " onmouseover="alert(1) <strong>text</strong>';
        update_post_meta($id, 'synonym', $text);
        update_post_meta($id, 'titleLang', 'en" onfocus="alert(1)');
        self::assertSame(esc_html($text), do_shortcode('[synonym id="' . $id . '"]'));
        $output = do_shortcode('[fau_abbr id="' . $id . '"]');
        $dom = new \DOMDocument();
        $dom->loadHTML($output);
        $abbr = $dom->getElementsByTagName('abbr')->item(0);
        self::assertSame($text, $abbr->getAttribute('title'));
        self::assertFalse($abbr->hasAttribute('onmouseover'));
        self::assertFalse($abbr->hasAttribute('onfocus'));
        self::assertSame(0, $dom->getElementsByTagName('img')->length);
    }

    public function testPlaceholderRetainsSafeLegacyHtmlAndCoreBlocks(): void
    {
        $id = self::factory()->post->create([
            'post_type' => 'rrze_placeholder', 'post_status' => 'publish',
            'post_content' => '&lt;strong&gt;Legacy&lt;/strong&gt; &lt;a href="https://example.org/"&gt;Link&lt;/a&gt;',
        ]);
        $output = do_shortcode('[placeholder id="' . $id . '"]');
        self::assertStringContainsString('<strong>Legacy</strong>', $output);
        self::assertStringContainsString('href="https://example.org/"', $output);
        wp_update_post(['ID' => $id, 'post_content' => '<!-- wp:paragraph --><p><em>Block content</em></p><!-- /wp:paragraph -->']);
        self::assertStringContainsString('<em>Block content</em>', do_shortcode('[placeholder id="' . $id . '"]'));
    }

    public function testInlinePlaceholdersSanitizeDecodedContentAndHideWithdrawnReferences(): void
    {
        $id = self::factory()->post->create([
            'post_type' => 'rrze_placeholder', 'post_status' => 'publish',
            'post_content' => '&lt;img src=x onerror=alert(1)&gt;<strong>Visible</strong>',
        ]);
        $marker = '<placeholder data-placeholder-id="' . $id . '" title="REVIEW_SNAPSHOT">label</placeholder>';
        $output = \RRZE\Answers\main()->renderInlinePlaceholders('Before ' . $marker . ' after');
        self::assertStringNotContainsString('onerror', $output);
        self::assertStringContainsString('<strong>Visible</strong>', $output);
        wp_update_post(['ID' => $id, 'post_status' => 'draft']);
        self::assertSame('Before  after', \RRZE\Answers\main()->renderInlinePlaceholders('Before ' . $marker . ' after'));
        self::assertSame('Before  after', \RRZE\Answers\main()->renderInlinePlaceholders('Before <placeholder data-placeholder-id="invalid" title="REVIEW_SNAPSHOT">label</placeholder> after'));
        self::assertSame('Before <strong>Legacy</strong> after', \RRZE\Answers\main()->renderInlinePlaceholders('Before <placeholder title="&lt;strong&gt;Legacy&lt;/strong&gt;">label</placeholder> after'));
    }

    public function testNestedPlaceholderReferencesStopAtCyclesAndCanRenderAgain(): void
    {
        $first = self::factory()->post->create(['post_type' => 'rrze_placeholder', 'post_status' => 'publish']);
        $second = self::factory()->post->create(['post_type' => 'rrze_placeholder', 'post_status' => 'publish']);
        wp_update_post(['ID' => $first, 'post_content' => 'REVIEW_FIRST <placeholder data-placeholder-id="' . $second . '">label</placeholder>']);
        wp_update_post(['ID' => $second, 'post_content' => 'REVIEW_SECOND <!-- wp:rrze-answers/placeholder ' . wp_json_encode(['id' => [$first]]) . ' /-->']);
        for ($i = 0; $i < 2; $i++) {
            $output = do_shortcode('[placeholder id="' . $first . '"]');
            self::assertSame(1, substr_count($output, 'REVIEW_FIRST'));
            self::assertSame(1, substr_count($output, 'REVIEW_SECOND'));
        }
    }
}
