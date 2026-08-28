<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\HtmlSanitizer;
use WP_UnitTestCase;

final class HtmlSanitizerTest extends WP_UnitTestCase
{
    public function testGlobalPostPolicyOnlyAddsLegacyPlaceholderMarkup(): void
    {
        $allowedHtml = wp_kses_allowed_html('post');

        self::assertArrayHasKey('placeholder', $allowedHtml);
        self::assertSame(
            [
                'class',
                'title',
                'lang',
                'data-placeholder-id',
                'data-placeholder-title',
            ],
            array_keys($allowedHtml['placeholder'])
        );

        self::assertArrayNotHasKey('input', $allowedHtml);
        self::assertArrayNotHasKey('select', $allowedHtml);
        self::assertArrayNotHasKey('form', $allowedHtml);
        self::assertArrayNotHasKey('svg', $allowedHtml);
        self::assertArrayNotHasKey('path', $allowedHtml);
        self::assertArrayNotHasKey('use', $allowedHtml);
        self::assertArrayNotHasKey('itemscope', $allowedHtml['div']);
        self::assertArrayNotHasKey('itemprop', $allowedHtml['div']);
    }

    public function testLegacyPlaceholderSurvivesPostSanitization(): void
    {
        $html = '<p>Before '
            . '<placeholder class="rrze-answers-placeholder" title="Office" lang="de" '
            . 'data-placeholder-id="42" data-placeholder-title="Contact" onclick="alert(1)">'
            . '<strong>Placeholder</strong></placeholder>'
            . '<script>alert(2)</script></p>';

        $sanitized = wp_kses_post($html);

        self::assertStringContainsString('<placeholder', $sanitized);
        self::assertStringContainsString('data-placeholder-id="42"', $sanitized);
        self::assertStringContainsString('data-placeholder-title="Contact"', $sanitized);
        self::assertStringContainsString('<strong>Placeholder</strong>', $sanitized);
        self::assertStringNotContainsString('onclick=', $sanitized);
        self::assertStringNotContainsString('<script', $sanitized);
    }

    public function testPluginMarkupPolicyPreservesOnlyRequiredExtensions(): void
    {
        $html = '<div itemscope itemtype="https://schema.org/FAQPage" itemprop="mainEntity" '
            . 'itemid="unused" onclick="alert(1)">'
            . '<summary itemscope itemprop="name" itemtype="https://schema.org/Question">Question</summary>'
            . '<input type="search" id="faq-search" class="rrze-answers-search__input" '
            . 'synonym="Search" data-minlen="3" autocomplete="off" onfocus="alert(2)">'
            . '<select id="faq-select" name="faq"><option value="42" selected>Answer</option></select>'
            . '<form action="https://attacker.example"><button type="submit">Submit</button></form>'
            . '<svg viewBox="0 0 10 10"><path d="M0 0"></path></svg>'
            . '</div>';

        $sanitized = HtmlSanitizer::sanitizePluginMarkup($html);

        self::assertStringContainsString('itemscope', $sanitized);
        self::assertStringContainsString('itemtype="https://schema.org/FAQPage"', $sanitized);
        self::assertStringContainsString('itemprop="mainEntity"', $sanitized);
        self::assertStringContainsString('<summary itemscope itemprop="name"', $sanitized);
        self::assertStringContainsString('<input type="search"', $sanitized);
        self::assertStringContainsString('data-minlen="3"', $sanitized);
        self::assertStringContainsString('autocomplete="off"', $sanitized);
        self::assertStringContainsString('<select id="faq-select" name="faq">', $sanitized);
        self::assertStringContainsString('<option value="42" selected>', $sanitized);

        self::assertStringNotContainsString('itemid=', $sanitized);
        self::assertStringNotContainsString('onclick=', $sanitized);
        self::assertStringNotContainsString('onfocus=', $sanitized);
        self::assertStringNotContainsString('<form', $sanitized);
        self::assertStringNotContainsString('<svg', $sanitized);
        self::assertStringNotContainsString('<path', $sanitized);
    }
}
