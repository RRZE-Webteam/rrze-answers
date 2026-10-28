<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use RRZE\Answers\Common\Sync\SynchronizedSourceRemovalService;
use RRZE\Answers\Main;
use WP_UnitTestCase;

final class SourceRemovalServiceTest extends WP_UnitTestCase
{
    private const SOURCE_IDENTIFIER = 'remote';

    private SynchronizedSourceRemovalService $removalService;

    public function set_up(): void
    {
        parent::set_up();

        $_GET = [];
        $_POST = [];
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->removalService = new SynchronizedSourceRemovalService();
    }

    public function tear_down(): void
    {
        $_GET = [];
        $_POST = [];

        parent::tear_down();
    }

    public function testRemovingSourceTrashesOnlyItsEntriesAndRetainsTerms(): void
    {
        $faqPostId = $this->createSynchronizedPost('faq', self::SOURCE_IDENTIFIER);
        $glossaryPostId = $this->createSynchronizedPost('glossary', self::SOURCE_IDENTIFIER);
        $otherSourcePostId = $this->createSynchronizedPost('faq', 'other');
        $localPostId = $this->createSynchronizedPost('faq', 'website');
        $category = wp_insert_term('Recoverable category', 'rrze_faq_category');

        self::assertIsArray($category);
        update_term_meta((int) $category['term_id'], 'source', self::SOURCE_IDENTIFIER);
        wp_set_object_terms($faqPostId, [(int) $category['term_id']], 'rrze_faq_category');
        wp_set_object_terms($localPostId, [(int) $category['term_id']], 'rrze_faq_category');

        $result = $this->removalService->remove(
            [self::SOURCE_IDENTIFIER],
            [self::SOURCE_IDENTIFIER => 'https://remote.example.org', 'other' => 'https://other.example.org']
        );

        self::assertIsArray($result);
        self::assertSame(2, $result['trashedCount']);
        self::assertSame([self::SOURCE_IDENTIFIER], $result['removedSourceIdentifiers']);
        self::assertSame('trash', get_post_status($faqPostId));
        self::assertSame('trash', get_post_status($glossaryPostId));
        self::assertSame('publish', get_post_status($otherSourcePostId));
        self::assertSame('publish', get_post_status($localPostId));
        self::assertNotNull(term_exists('Recoverable category', 'rrze_faq_category'));
        self::assertSame(
            ['Recoverable category'],
            wp_get_post_terms($faqPostId, 'rrze_faq_category', ['fields' => 'names'])
        );
    }

    public function testLaterTrashFailureRestoresEarlierEntries(): void
    {
        $faqPostId = $this->createSynchronizedPost(
            'faq',
            self::SOURCE_IDENTIFIER,
            'Trash succeeds'
        );
        $glossaryPostId = $this->createSynchronizedPost(
            'glossary',
            self::SOURCE_IDENTIFIER,
            'Trash fails'
        );

        add_filter(
            'pre_trash_post',
            static function ($trash, \WP_Post $post) {
                return $post->post_title === 'Trash fails' ? false : $trash;
            },
            10,
            2
        );

        $result = $this->removalService->remove(
            [self::SOURCE_IDENTIFIER],
            [self::SOURCE_IDENTIFIER => 'https://remote.example.org']
        );

        self::assertWPError($result);
        self::assertSame('source_removal_trash_failed', $result->get_error_code());
        self::assertSame('publish', get_post_status($faqPostId));
        self::assertSame('publish', get_post_status($glossaryPostId));
    }

    public function testEveryIdentifierIsValidatedBeforePostsAreChanged(): void
    {
        $postId = $this->createSynchronizedPost('faq', self::SOURCE_IDENTIFIER);

        $result = $this->removalService->remove(
            [self::SOURCE_IDENTIFIER, 'unknown'],
            [self::SOURCE_IDENTIFIER => 'https://remote.example.org']
        );

        self::assertWPError($result);
        self::assertSame('source_removal_not_registered', $result->get_error_code());
        self::assertSame('publish', get_post_status($postId));
    }

    public function testRemovalOnlyTouchesTheCurrentMultisiteSite(): void
    {
        $otherSiteId = self::factory()->blog->create();

        switch_to_blog($otherSiteId);
        $otherSitePostId = $this->createSynchronizedPost('faq', self::SOURCE_IDENTIFIER);
        restore_current_blog();

        $currentSitePostId = $this->createSynchronizedPost('faq', self::SOURCE_IDENTIFIER);

        $result = $this->removalService->remove(
            [self::SOURCE_IDENTIFIER],
            [self::SOURCE_IDENTIFIER => 'https://remote.example.org']
        );

        self::assertIsArray($result);
        self::assertSame('trash', get_post_status($currentSitePostId));

        switch_to_blog($otherSiteId);
        self::assertSame('publish', get_post_status($otherSitePostId));
        restore_current_blog();
    }

    public function testMainRemovesSettingsOnlyAfterSuccessfulCleanup(): void
    {
        $postId = $this->createSynchronizedPost('faq', self::SOURCE_IDENTIFIER);
        $storedOptions = [
            'registeredDomains' => [
                self::SOURCE_IDENTIFIER => 'https://remote.example.org',
                'other' => 'https://other.example.org',
            ],
            'faq_categories_' . self::SOURCE_IDENTIFIER => ['general'],
            'glossary_categories_' . self::SOURCE_IDENTIFIER => ['terms'],
        ];
        delete_option('rrze-answers');
        add_option('rrze-answers', $storedOptions);
        $_GET['tab'] = 'domains';
        $_POST['del_domain_1'] = self::SOURCE_IDENTIFIER;
        $_POST['rrze-answers_settings_save'] = wp_create_nonce(
            'rrze-answers_settings_save_rrze-answers'
        );

        $updatedOptions = (new Main())->switchTask(['new_url' => '']);

        self::assertSame('trash', get_post_status($postId));
        self::assertSame(
            ['other' => 'https://other.example.org'],
            $updatedOptions['registeredDomains']
        );
        self::assertArrayNotHasKey(
            'faq_categories_' . self::SOURCE_IDENTIFIER,
            $updatedOptions
        );
        self::assertArrayNotHasKey(
            'glossary_categories_' . self::SOURCE_IDENTIFIER,
            $updatedOptions
        );
    }

    public function testMainKeepsSettingsWhenCleanupFails(): void
    {
        $postId = $this->createSynchronizedPost(
            'faq',
            self::SOURCE_IDENTIFIER,
            'Trash fails'
        );
        $storedOptions = [
            'registeredDomains' => [
                self::SOURCE_IDENTIFIER => 'https://remote.example.org',
            ],
            'faq_categories_' . self::SOURCE_IDENTIFIER => ['general'],
        ];
        delete_option('rrze-answers');
        add_option('rrze-answers', $storedOptions);
        $_GET['tab'] = 'domains';
        $_POST['del_domain_1'] = self::SOURCE_IDENTIFIER;
        $_POST['rrze-answers_settings_save'] = wp_create_nonce(
            'rrze-answers_settings_save_rrze-answers'
        );

        add_filter('pre_trash_post', static fn () => false);

        $updatedOptions = (new Main())->switchTask(['new_url' => '']);

        self::assertSame('publish', get_post_status($postId));
        self::assertSame(
            [self::SOURCE_IDENTIFIER => 'https://remote.example.org'],
            $updatedOptions['registeredDomains']
        );
        self::assertSame(
            ['general'],
            $updatedOptions['faq_categories_' . self::SOURCE_IDENTIFIER]
        );
    }

    public function testMainIgnoresUnauthorizedRemovalRequest(): void
    {
        $postId = $this->createSynchronizedPost('faq', self::SOURCE_IDENTIFIER);
        $storedOptions = [
            'registeredDomains' => [
                self::SOURCE_IDENTIFIER => 'https://remote.example.org',
            ],
        ];
        delete_option('rrze-answers');
        add_option('rrze-answers', $storedOptions);
        $_GET['tab'] = 'domains';
        $_POST['del_domain_1'] = self::SOURCE_IDENTIFIER;

        $updatedOptions = (new Main())->switchTask(['new_url' => '']);

        self::assertSame('publish', get_post_status($postId));
        self::assertSame(
            [self::SOURCE_IDENTIFIER => 'https://remote.example.org'],
            $updatedOptions['registeredDomains']
        );
    }

    private function createSynchronizedPost(
        string $contentType,
        string $sourceIdentifier,
        string $title = 'Synchronized entry'
    ): int {
        return self::factory()->post->create([
            'post_type' => 'rrze_' . $contentType,
            'post_status' => 'publish',
            'post_title' => $title,
            'meta_input' => [
                'source' => $sourceIdentifier,
                'remoteID' => wp_rand(1, 1000000),
                'remoteChanged' => 100,
            ],
        ]);
    }
}
