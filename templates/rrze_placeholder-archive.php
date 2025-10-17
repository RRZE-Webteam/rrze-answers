<?php
/* 
Template Name: CPT placeholder Archive Template
*/
use RRZE\Answers\Common\Tools;


get_header();
?>

<main id="main" class="site-main rrze-answers archive">
    <div id="content"><div class="content-container">
        <h2><?php echo __('Placeholders', 'rrze-answers'); ?></h2>
        <?php
        if (have_posts()) {
            echo '<table class="placeholder">';
            while (have_posts()) {
                the_post();
                echo '<tr>';
                echo '<th scope="row">' . get_the_title() . '</th>' ;
                echo '<td>' . get_post_meta( $post->ID, 'placeholder', TRUE ) . Tools::getPronunciation($post->ID) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>
    </div></div>
</main>

<?php get_footer(); ?>
