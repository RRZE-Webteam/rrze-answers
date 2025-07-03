<?php
/**
 * This is part of the templates for displaying the FAQ
 *
 * @package WordPress
 * @subpackage FAU
 * @since FAU 1.0
 */

namespace RRZE\Answers;

use RRZE\Answers\Tools;

$postID = get_the_ID();
$tools = new Tools();
$headerID = $tools->getHeaderID($postID);

$cats = $tools->getTermLinks($postID, 'rrze_faq_category');
$tags = $tools->getTermLinks($postID, 'rrze_faq_tag');
$aLinkedPage = $tools->getLinkedPage($postID);

$content = '';
$content .= '<article>';
$content .= '<div id="content"><div class="content-container">';
$content .= '<header>';
$content .= '<h1 id="' . esc_attr($headerID) . '">' . esc_html(get_the_title()) . '</h1>';
$content .= '</header>';
$content .= apply_filters('the_content', get_the_content());
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

    $linkHTML = sprintf(
        '<a href="%1$s">%2$s</a>',
        $url,
        $title
    );

    $content .= '<span class="post-meta-context">' . $linkHTML . '</span>';
}

$content .= '</p></footer>';
$content .= '</div></div>';
$content .= '</article>';

$masonry = false;
$color = '';
$additional_class = '';

echo wp_kses_post($tools->renderFaqWrapper($postID, $content, $headerID, $masonry, $color, $additional_class));
