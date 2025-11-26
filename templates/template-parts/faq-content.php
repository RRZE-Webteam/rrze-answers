<?php
/**
 * This is part of the templates for displaying the FAQ
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

namespace RRZE\Answers\Common;

use RRZE\Answers\Common\Tools;

$postID = get_the_ID();
$tools = new Tools();
$headerID = $tools->getHeaderID($postID);
$source = get_post_meta($postID, "source", true);

$cats = $tools->getTermLinks($postID, 'rrze_faq_category');
$tags = $tools->getTermLinks($postID, 'rrze_faq_tag');
$aLinkedPage = $tools->getLinkedPage($postID);

$bSchema = ($source === 'website');


$content = '';
$content .= ($bSchema ? '<div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">' : '');
$content .= '<article>';
$content .= '<div id="content"><div class="content-container">';
$content .= '<header>';
$content .= '<h1 id="' . esc_attr($headerID) . '"' . ($bSchema ? ' itemprop="name"' : '') . '>' . esc_html(get_the_title()) . '</h1>';
$content .= '</header>';

if ($bSchema) {
    $content .= '<div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">';
    $content .= '<div itemprop="text">';
}

$content .= apply_filters('the_content', get_the_content());

if ($bSchema) {
    $content .= '</div></div>'; // text + acceptedAnswer
}

$content .= '<footer><p class="meta-footer">';

if ($cats) {
    $content .= '<span class="post-meta-categories">' . esc_html__('Categories', 'rrze-answers') . ': ' . wp_kses_post($cats) . '</span> ';
}
if ($tags) {
    $content .= '<span class="post-meta-tags">' . esc_html__('Tags', 'rrze-answers') . ': ' . wp_kses_post($tags) . '</span>';
}

if (!empty($aLinkedPage)) {
    $url = isset($aLinkedPage['url']) ? esc_url($aLinkedPage['url']) : '';
    $title = isset($aLinkedPage['title']) ? esc_html($aLinkedPage['title']) : '';
    $linkHTML = sprintf('<a href="%1$s">%2$s</a>', $url, $title);
    $content .= '<span class="post-meta-context">' . $linkHTML . '</span>';
}

$content .= '</p></footer>';
$content .= '</div></div>';
$content .= '</article>';
$content .= ($bSchema ? '</div>' : ''); // mainEntity

$masonry = false;
$color = '';
$additional_class = '';

echo wp_kses_post($tools->renderWrapper('faq', $content, $headerID, $masonry, $color, $additional_class, $bSchema, $postID));
