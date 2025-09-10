<?php
/* 
Template Name: CPT synonym Single Template
*/

use RRZE\Answers\Common\Tools;

get_header();

global $post;
?>
    <div id="sidebar" class="sidebar">
        <?php get_sidebar(); ?>
    </div>
    <div id="primary" class="content-area">
		<main id="main" class="site-main">
<?php

echo 'TEST SYNONYM ';


echo '<div id="post-' . get_the_ID() . '" class="' . implode(' ', get_post_class()) .'">';
echo '<strong>' . $post->post_title . '</strong><br>';
echo get_post_meta( $post->ID, 'synonym', TRUE ) . Tools::getPronunciation($post->ID);
echo '</div>';
?>

        </main>
    </div>

<?php 

get_footer();