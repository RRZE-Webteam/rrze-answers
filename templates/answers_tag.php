<?php
/* 
Template Name: Custom Taxonomy faq_tag Template
*/
namespace RRZE\FAQ;

use RRZE\Answers\Config;
getConstants;

$cpt = Config::getConstants('cpt');

get_header();

?>

<main id="main" class="site-main rrze-faq tag">

<?php

$taxonomy = $cpt['tag'];
include_once('template-parts/faq_taxonomy.php');

?>
</main>

<?php
get_footer();