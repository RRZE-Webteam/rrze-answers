<?php

$atts = '';
foreach($attributes as $key => $value){
    $atts .= $key . '="' . $value . '" ';
}

echo do_shortcode('[glossary ' . $atts . ']');
