<?php
/**
 * This is part of the templates for displaying the placeholder
 *
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
*/

namespace RRZE\Answers\Common;


echo '<div id="post-' . esc_attr(get_the_ID()) . '" class="' . esc_attr(implode(' ', get_post_class())) .'">';

?>



<h1 id="droppoint" class="glossary-title" itemprop="title"><?php the_title(); ?></h1>


<?php 

the_content(); 

// wp_enqueue_style('rrze-answers-css');
// wp_enqueue_script('rrze-answers-accordion');

echo '</div>';

