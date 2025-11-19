<?php
/* 
Template Name: Custom Taxonomy faq_tag Template
*/
get_header();

?>

<main id="main" class="site-main rrze-answers tag">

<?php

$post_type = 'rrze_faq';
$taxonomy = 'rrze_faq_tag';
include_once('template-parts/taxonomy.php');

?>
</main>

<?php
get_footer();