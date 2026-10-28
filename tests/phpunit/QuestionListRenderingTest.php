<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use WP_UnitTestCase;

/**
 * Public shortcode behaviour shared by FAQ and glossary output.
 */
class QuestionListRenderingTest extends WP_UnitTestCase
{
    /**
     * Start each example as an independent page with native accordion markup.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        (new \ReflectionProperty(\RRZE\Answers\Common\Content\QuestionListRenderer::class, 'usedAnchorIds'))->setValue(null, []);
        add_filter('rrze_answers_accordion_backend', [$this, 'nativeAccordion']);
    }

    protected function tearDown(): void
    {
        remove_filter('rrze_answers_accordion_backend', [$this, 'nativeAccordion']);
        parent::tearDown();
    }

    public function nativeAccordion(): string
    {
        return 'native';
    }

    /** @dataProvider contentTypes */
    public function testSelectionOrderAndAlphabeticalIndex(string $type, string $groupAttribute): void
    {
        $entries = $this->createEntries($type);
        $selected = ' id="' . $entries['beta'] . ',' . $entries['alpha'] . '"';
        $output = do_shortcode('[' . $type . $selected . ']');
        self::assertLessThan(strpos($output, 'Alpha entry'), strpos($output, 'Beta entry'));
        self::assertStringContainsString('id="custom-beta"', $output);
        self::assertStringNotContainsString('Gamma entry', $output);

        $output = do_shortcode('[' . $type . $selected . ' ' . $groupAttribute . '="a-z"]');
        self::assertLessThan(strpos($output, 'Beta entry'), strpos($output, 'Alpha entry'));
        self::assertStringContainsString('class="letters list-icons"', $output);
        self::assertMatchesRegularExpression('/href="#rrze-answers-index-\d+-A"/', $output);
    }

    /** @dataProvider contentTypes */
    public function testCategoryTagAndLanguageFiltersAreCombined(string $type): void
    {
        $this->createEntries($type);
        $output = do_shortcode('[' . $type . ' category="group-a" tag="featured" lang="de"]');
        self::assertStringContainsString('Alpha answer', $output);
        self::assertStringNotContainsString('Beta answer', $output);
        self::assertStringNotContainsString('Gamma answer', $output);
    }

    /** @dataProvider contentTypes */
    public function testSortFieldAndDescendingTitleOrder(string $type): void
    {
        $this->createEntries($type);
        $output = do_shortcode('[' . $type . ' sort="sortfield" order="asc"]');
        self::assertLessThan(strpos($output, 'Alpha entry'), strpos($output, 'Beta entry'));
        self::assertLessThan(strpos($output, 'Gamma entry'), strpos($output, 'Alpha entry'));

        $output = do_shortcode('[' . $type . ' sort="title" order="desc"]');
        self::assertLessThan(strpos($output, 'Beta entry'), strpos($output, 'Gamma entry'));
        self::assertLessThan(strpos($output, 'Alpha entry'), strpos($output, 'Beta entry'));
    }

    /** @dataProvider contentTypes */
    public function testFlatDisplayOptionsAndSchema(string $type): void
    {
        $entries = $this->createEntries($type);
        $output = do_shortcode('[' . $type . ' id="' . $entries['alpha']
            . '" hide="accordion title" hstart="4" masonry="1" show="search" class="nat custom-list"]');
        self::assertStringNotContainsString('<details', $output);
        self::assertStringContainsString('<h4', $output);
        self::assertStringContainsString('class="screen-reader-text">', $output);
        self::assertStringContainsString('rrze-answers-masonry', $output);
        self::assertStringContainsString('custom-list', $output);
        self::assertStringContainsString('rrze-answers-search', $output);
        self::assertStringContainsString('<strong>Alpha answer</strong>', $output);
        self::assertStringContainsString(
            'itemtype="https://schema.org/' . ($type === 'faq' ? 'Question' : 'DefinedTerm') . '"',
            $output
        );

        $remoteOutput = do_shortcode('[' . $type . ' id="' . $entries['beta'] . '" hide="accordion"]');
        self::assertStringNotContainsString('itemtype=', $remoteOutput);
    }

    /** @dataProvider contentTypes */
    public function testGroupedNavigationKeepsEntriesAndAnchors(string $type, string $groupAttribute): void
    {
        $this->createEntries($type);
        foreach (['category tagcloud', 'tag a-z', 'category tabs'] as $view) {
            $output = do_shortcode('[' . $type . ' ' . $groupAttribute . '="' . $view . '"]');
            self::assertStringContainsString('Alpha answer', $output);
            self::assertStringContainsString('Beta answer', $output);
            self::assertStringContainsString('Gamma answer', $output);
            self::assertMatchesRegularExpression('/id="rrze-answers-index-\d+-term-\d+"/', $output);
            if ($view === 'category tagcloud') {
                self::assertStringContainsString('rrze-answers-tagcloud-item', $output);
            }
            if ($view === 'tag a-z') {
                self::assertStringContainsString('class="letters list-icons"', $output);
            }
        }
    }

    public function testSourceFilterAndLegacyGlossaryAlias(): void
    {
        $this->createEntries('faq');
        $output = do_shortcode('[faq domain="remote"]');
        self::assertStringContainsString('Beta answer', $output);
        self::assertStringNotContainsString('Alpha answer', $output);
        self::assertStringNotContainsString('Gamma answer', $output);

        $entries = $this->createEntries('glossary');
        self::assertStringContainsString('Alpha answer', do_shortcode('[fau_glossar id="' . $entries['alpha'] . '"]'));
    }

    /** @dataProvider contentTypes */
    public function testContentTypeDefaultsAndLegacyColorTokens(string $type): void
    {
        $entries = $this->createEntries($type);
        $shortcode = '[' . $type . ' id="' . $entries['alpha'] . '"';
        $output = do_shortcode($shortcode . ']');
        self::assertStringContainsString('is-' . ($type === 'faq' ? 'fau' : 'tk'), $output);

        $output = do_shortcode($shortcode . ' class="tf tk fau"]');
        self::assertStringContainsString('is-' . ($type === 'faq' ? 'tf' : 'tk'), $output);
        self::assertStringContainsString('class="rrze-answers ' . ($type === 'faq' ? 'tk' : 'tf') . ' fau"', $output);
    }

    /** @dataProvider nestedSources */
    public function testNestedGlossaryDoesNotChangeOuterSchema(string $outerSource, string $innerSource): void
    {
        $entries = $this->createEntries('glossary');
        update_post_meta($entries['alpha'], 'source', $outerSource);
        update_post_meta($entries['beta'], 'source', $innerSource);
        wp_update_post([
            'ID' => $entries['alpha'],
            'post_content' => '<p>Outer answer</p>[glossary id="' . $entries['beta'] . '" hide="accordion"]',
        ]);

        $output = do_shortcode('[glossary category="group-a" lang="de" hide="accordion"]');
        self::assertStringContainsString('Outer answer', $output);
        self::assertStringContainsString('Beta answer', $output);
        self::assertStringStartsWith(
            $outerSource === 'website'
                ? '<div itemscope itemtype="https://schema.org/DefinedTermSet"'
                : '<div class="rrze-answers"',
            $output
        );
    }

    public function nestedSources(): array
    {
        return [['website', 'remote'], ['remote', 'website']];
    }

    public function contentTypes(): array
    {
        return [['faq', 'glossary'], ['glossary', 'register']];
    }

    /** @return array<string, int> */
    protected function createEntries(string $type): array
    {
        $postType = 'rrze_' . $type;
        $categoryA = self::factory()->term->create(['taxonomy' => $postType . '_category', 'name' => 'Alpha group', 'slug' => 'group-a']);
        $categoryB = self::factory()->term->create(['taxonomy' => $postType . '_category', 'name' => 'Beta group', 'slug' => 'group-b']);
        $tag = self::factory()->term->create(['taxonomy' => $postType . '_tag', 'name' => 'Featured', 'slug' => 'featured']);
        $ids = [];
        foreach (['alpha', 'beta', 'gamma'] as $name) {
            $id = self::factory()->post->create([
                'post_type' => $postType,
                'post_status' => 'publish',
                'post_title' => ucfirst($name) . ' entry',
                'post_content' => '<p><strong>' . ucfirst($name) . ' answer</strong></p>',
            ]);
            $ids[$name] = $id;
            update_post_meta($id, 'source', $name === 'beta' ? 'remote' : 'website');
            update_post_meta($id, 'lang', $name === 'beta' ? 'en' : 'de');
            update_post_meta($id, 'sortfield', ['alpha' => '2', 'beta' => '1', 'gamma' => '3'][$name]);
            wp_set_object_terms($id, [$name === 'gamma' ? $categoryB : $categoryA], $postType . '_category');
            wp_set_object_terms($id, [$tag], $postType . '_tag');
        }
        update_post_meta($ids['beta'], 'anchorfield', 'custom-beta');
        return $ids;
    }
}
