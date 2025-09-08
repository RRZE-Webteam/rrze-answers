<?php
/* 
Template Name: Custom Taxonomy faq_tag Template
*/
get_header();

?>

<main id="main" class="site-main rrze-answers tag">

<?php

$taxonomy = 'rrze_faq_tag';
include_once('template-parts/faq-taxonomy.php');

?>
</main>

<?php
get_footer();