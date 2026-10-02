<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

/**
 * Translate legacy FAQ/glossary attributes into a common set of list options.
 */
final class QuestionListAttributes
{
    public static function normalize($attributes, array $settings, string $type): array
    {
        $attributes = empty($attributes) ? [] : array_map('sanitize_text_field', $attributes);
        $groupAttribute = $type === 'faq' ? 'glossary' : 'register';
        $styleAttribute = $groupAttribute . 'style';

        if (isset($attributes[$groupAttribute])) {
            $grouping = '';
            foreach (explode(' ', $attributes[$groupAttribute]) as $part) {
                if (in_array($part, ['category', 'tag'], true)) {
                    $grouping = $part;
                } elseif (in_array($part, ['a-z', 'tabs', 'tagcloud'], true)) {
                    $attributes[$styleAttribute] = $part;
                }
            }
            $attributes[$groupAttribute] = $grouping;
        }

        foreach (explode(' ', $attributes['hide'] ?? '') as $part) {
            if ($part === 'title') {
                $attributes['hide_title'] = true;
            } elseif (in_array($part, ['accordion', 'accordeon'], true)) {
                $attributes['hide_accordion'] = true;
            } elseif ($part === $groupAttribute) {
                $attributes[$styleAttribute] = '';
            }
        }

        $showFlags = ['expand-all-link' => 'expand_all_link', 'load-open' => 'load_open', 'search' => 'search'];
        foreach (explode(' ', $attributes['show'] ?? '') as $part) {
            if (isset($showFlags[$part])) {
                $attributes[$showFlags[$part]] = true;
            }
        }

        $attributes['additional_class'] = $attributes['additional_class'] ?? '';
        if (isset($attributes['class'])) {
            // The two shortcode families historically use different faculty tokens.
            $colors = ['med', 'nat', 'phil', 'rw', $type === 'faq' ? 'tf' : 'tk'];
            foreach (explode(' ', $attributes['class']) as $part) {
                if (in_array($part, $colors, true)) {
                    $attributes['color'] = $part;
                } else {
                    $attributes['additional_class'] .= ' ' . $part;
                }
            }
        }

        if (!in_array($attributes['sort'] ?? '', ['title', 'id', 'sortfield'], true)) {
            $attributes['sort'] = 'title';
        }

        $defaults = [];
        foreach ($settings as $name => $setting) {
            if ($name !== 'block') {
                $defaults[$name] = $setting['default'];
            }
        }
        $attributes = shortcode_atts($defaults, $attributes);
        $attributes['grouping'] = (string) $attributes[$groupAttribute];
        $attributes['index_style'] = (string) $attributes[$styleAttribute];
        unset($attributes[$groupAttribute], $attributes[$styleAttribute]);

        // Retain the existing shortcode truthiness, including older saved blocks.
        foreach (['hide_title', 'hide_accordion', 'expand_all_link', 'load_open', 'masonry', 'search'] as $flag) {
            $attributes[$flag] = !empty($attributes[$flag]);
        }
        $attributes['hstart'] = (int) $attributes['hstart'];

        return $attributes;
    }
}
