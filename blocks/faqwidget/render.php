<?php
use RRZE\Answers\Common\Tools;

$id = (!empty($attributes['id']) ? $attributes['id'] : 0);

if (!$id){
    $catID = (!empty($attributes['catID']) ? $attributes['catID'] : 0);
    $id = Tools::get_random_faq_id($catID);
}

$hide = '';

if (!empty($attributes['hide_title']) && $attributes['hide_title']){
    $hide = ' hide="title accordion"';
}

echo do_shortcode('[faq id=' . $id . $hide . ']');
