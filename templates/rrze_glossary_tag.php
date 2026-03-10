<?php
/* 
Template Name: Custom Taxonomy rrze_glossary_tag Template
*/

get_header();

?>

<main id="main" class="site-main rrze-answers tag">

<?php

$post_type = 'rrze_glossary';
$taxonomy = 'rrze_glossary_tag';
include_once('template-parts/taxonomy.php');
?>
</main>

<?php
get_footer();