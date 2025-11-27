<?php
use RRZE\Answers\Common\Tools;

// return function($attributes) {
//     return Tools::render_faq_block($attributes);
// };


$atts = '';
foreach($attributes as $key => $value){
    $atts .= $key . '="' . $value . '" ';
}

echo do_shortcode('[faq ' . $atts . ']');
