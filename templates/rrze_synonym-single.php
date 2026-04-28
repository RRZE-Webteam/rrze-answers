<?php
/**
 * The template for displaying all synonyms
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

use RRZE\Answers\Common\Tools;

get_header();
?>

<main id="main" class="site-main rrze-answers archive">
    <div id="content"><div class="content-container">
        <h2><?php echo __('Synonym', 'rrze-answers'); ?></h2>

        <?php
        if (have_posts()) {
            while (have_posts()) {
                the_post();
                $synonym = get_post_meta(get_the_ID(), 'synonym', true);
                $pronunciation = Tools::getPronunciation(get_the_ID());
                printf(
                    '<p>%s: %s%s</p>',
                    esc_html(get_the_title()),
                    esc_html($synonym),
                    wp_kses_post($pronunciation)
                );
            }
        } else {
            echo '<p>' . esc_html__('No synonyms found.', 'rrze-answers') . '</p>';
        }
        ?>

        <?php
        // Pagination
        the_posts_pagination([
            'mid_size'           => 2,
            'end_size'           => 1,
            'prev_text'          => '← ' . esc_html__('Previous', 'fau'),
            'next_text'          => esc_html__('Next', 'fau') . ' →',
            'screen_reader_text' => esc_html__('synonyms navigation', 'fau'),
        ]);
        ?>
    </div></div>
</main>

<?php get_footer(); ?>
