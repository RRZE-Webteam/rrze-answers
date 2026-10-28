<?php

declare(strict_types=1);

namespace RRZE\Answers\Tests;

use OverflowException;
use WP_UnitTestCase;

/** Bound settings-save reinitialization so an infinite hook loop fails safely. */
final class RolloutSlugChangeTest extends WP_UnitTestCase
{
    /**
     * Require one slug edit to finish without indefinitely extending its hooks.
     *
     * Both plugin generations re-fire init and emit duplicate-block notices.
     * WordPress's expectedIncorrectUsage handling suppresses only the known
     * duplicate-registration diagnostic for this probe. A separate process
     * contains re-registered hooks; hard rewrite writes are disabled locally.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedIncorrectUsage WP_Block_Type_Registry::register
     */
    public function testChangingAnExistingSlugFinishesWithinBoundedInitialization(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        delete_option('rrze-answers');
        add_option('rrze-answers', ['custom_faq_slug' => 'rollout-old', 'registeredDomains' => []]);
        $_GET['tab'] = 'website';
        $initializations = 0;
        $boundedFailure = null;
        $updated = false;

        /**
         * Abort before a twenty-first repeated init cycle can grow more hooks.
         *
         * @throws OverflowException When a single settings save does not settle.
         */
        $limitInitialization = static function () use (&$initializations): void {
            $initializations++;
            if ($initializations > 20) {
                throw new OverflowException('One slug update exceeded 20 init cycles; repeated CPT construction keeps adding option hooks.');
            }
        };
        add_action('init', $limitInitialization, -1000);
        add_filter('flush_rewrite_rules_hard', '__return_false');

        try {
            $updated = update_option('rrze-answers', ['custom_faq_slug' => 'rollout-new', 'registeredDomains' => []]);
        } catch (OverflowException $exception) {
            $boundedFailure = $exception->getMessage();
        } finally {
            remove_action('init', $limitInitialization, -1000);
            remove_filter('flush_rewrite_rules_hard', '__return_false');
        }

        self::assertNull($boundedFailure, (string) $boundedFailure);
        self::assertTrue($updated);
        self::assertLessThanOrEqual(20, $initializations);
        self::assertSame('rollout-new', get_option('rrze-answers')['custom_faq_slug']);
        self::assertSame('rollout-new', get_post_type_object('rrze_faq')->rewrite['slug']);
    }
}
