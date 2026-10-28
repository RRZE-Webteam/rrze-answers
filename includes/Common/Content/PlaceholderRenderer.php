<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Content;

defined('ABSPATH') || exit;

/**
 * Render legacy placeholder HTML consistently in blocks, shortcodes and text.
 */
final class PlaceholderRenderer
{
    /** @var array<string, bool> */
    private static array $rendering = [];

    public static function render(\WP_Post $post): string
    {
        if (!EntryQuery::isVisible($post, 'rrze_placeholder')) {
            return '';
        }

        $key = get_current_blog_id() . ':' . $post->ID;
        if (isset(self::$rendering[$key])) {
            return '';
        }

        self::$rendering[$key] = true;
        try {
            // Older entries contain encoded HTML. Sanitize after decoding,
            // before trusted WordPress block and shortcode renderers run.
            $content = wp_kses_post(html_entity_decode($post->post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            return (string) apply_filters('the_content', $content);
        } finally {
            unset(self::$rendering[$key]);
        }
    }

    public static function replaceInline(string $content): string
    {
        $rendered = preg_replace_callback(
            '/<placeholder\b([^>]*)>.*?<\/placeholder>/is',
            static function (array $matches): string {
                $tag = new \WP_HTML_Tag_Processor('<placeholder' . $matches[1] . '>');
                if (!$tag->next_tag()) {
                    return '';
                }

                $reference = $tag->get_attribute('data-placeholder-id');
                if ($reference !== null) {
                    // An unavailable reference must not disclose its saved title snapshot.
                    $posts = EntryQuery::byIds('rrze_placeholder', is_string($reference) && ctype_digit($reference) ? $reference : '');
                    return $posts === [] ? '' : self::unwrapParagraph(self::render($posts[0]));
                }

                // Markers predating ID references store their content in title.
                $title = $tag->get_attribute('title');
                return is_string($title)
                    ? self::unwrapParagraph(wp_kses_post(wpautop($title)))
                    : '';
            },
            $content
        );

        return $rendered ?? $content;
    }

    private static function unwrapParagraph(string $html): string
    {
        $html = trim($html);
        if (!preg_match('/^<p\b[^>]*>(.*)<\/p>$/is', $html, $matches)) {
            return $html;
        }

        $inner = trim($matches[1]);
        if ($inner !== '' && preg_match('/<\/(?:p|div|h[1-6]|blockquote|figure)\s*>/i', $inner)) {
            return $html;
        }

        return $inner;
    }
}
