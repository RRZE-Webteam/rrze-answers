<?php
/* 
Template Name: Custom Taxonomy faq_category Template
*/

get_header();

?>

<main id="main" class="site-main rrze-answers category">

<?php

$post_type = 'rrze_faq';
$taxonomy = 'rrze_faq_category';
include_once('template-parts/taxonomy.php');
?>
</main>

<?php
get_footer();