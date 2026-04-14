<?php
/* 
Template Name: CPT placeholder Archive Template
*/
use RRZE\Answers\Common\Tools;

get_header();
?>

<main id="main" class="site-main rrze-answers archive">
    <div id="content">
        <div class="content-container">
            <h2><?php echo __('Placeholder', 'rrze-answers'); ?></h2>

            <?php
            if (have_posts()) {
                echo '<table class="rrze_placeholder">';
                while (have_posts()) {
                    the_post();
                    echo '<tr>';
                    echo '<th scope="row">' . get_the_title() . '</th>';
                    // echo '<td>' . get_post_meta($post->ID, 'synonym', true) . Tools::getPronunciation($post->ID) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';

                // Pagination
                echo '<nav class="pagination">';
                echo paginate_links(array(
                    'mid_size' => 2,
                    'prev_text' => '← ' . esc_html__('Previous', 'fau'),
                    'next_text' => esc_html__('Next', 'fau') . ' →',
                ));
                echo '</nav>';
            }
            ?>

        </div>
    </div>
</main>

<?php get_footer(); ?>