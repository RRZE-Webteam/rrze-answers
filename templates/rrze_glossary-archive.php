<?php
/**
 * The template for displaying all FAQ
 *
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
*/

get_header();
?>

<main id="main" class="site-main rrze-answers archive">
    <div id="content"><div class="content-container">
        <h2><?php echo __('Glossary', 'rrze-answers'); ?></h2>
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
        }
        ?>
        </ul>
    </div></div>
</main>

<?php get_footer(); ?>
