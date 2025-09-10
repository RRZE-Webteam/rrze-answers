<?php
/**
 * This is part of the templates for displaying the glossary entries
 *
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
*/

namespace RRZE\Answers\Common;

use RRZE\Answers\Common\Tools;
$tools = new Tools();


echo '<div id="post-' . esc_attr(get_the_ID()) . '" class="' . esc_attr(implode(' ', get_post_class())) .'">';

?>



<h1 id="droppoint" class="glossary-title" itemprop="title"><?php the_title(); ?></h1>


<?php 

$postID = get_the_ID();
$cats = wp_kses_post($tools->getTermLinks( $postID, 'glossary_category' ));
$tags = wp_kses_post($tools->getTermLinks( $postID, 'glossary_tag' ));            
$details = '<article class="news-details">
<!-- rrze-glossary --><p id="rrze-glossary" class="meta-footer">'
. ( $cats ? '<span class="post-meta-categories"> '. __( 'Categories', 'rrze-glossary' ) . ': ' . $cats . '</span>' : '' )
. ( $tags ? '<span class="post-meta-tags"> '. __( 'Tags', 'rrze-glossary' ) . ': ' . $tags . '</span>' : '' )
. '</p></article>';

the_content(); 
echo $details;

echo '</div>';

