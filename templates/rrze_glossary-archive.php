<?php
/**
 * The template for displaying all glossary entries
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

get_header();
?>

<main id="main" class="site-main rrze-answers archive">
    <div id="content"><div class="content-container">
        <h2>FAQ</h2>

        <ul>
        <?php
        if (have_posts()) {
            while (have_posts()) {
                the_post();
                printf(
                    '<li><a href="%s">%s</a></li>',
                    esc_url(get_the_permalink()),
                    esc_html(get_the_title())
                );
            }
        } else {
            echo '<li>' . esc_html__('Keine FAQs gefunden.', 'fau') . '</li>';
        }
        ?>
        </ul>

        <?php
        // Pagination
        the_posts_pagination([
            'mid_size'           => 2,
            'end_size'           => 1,
            'prev_text'          => '← ' . esc_html__('Previous', 'fau'),
            'next_text'          => esc_html__('Next', 'fau') . ' →',
            'screen_reader_text' => esc_html__('FAQ navigation', 'fau'),
        ]);
        ?>
    </div></div>
</main>

<?php get_footer(); ?>
