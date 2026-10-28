<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Content;

defined('ABSPATH') || exit;

/**
 * Select entries for public embeds, independently of the current user's role.
 */
final class EntryQuery
{
    public static function isVisible($post, string $postType): bool
    {
        return $post instanceof \WP_Post
            && $post->post_type === $postType
            && $post->post_status === 'publish'
            && !post_password_required($post);
    }

    /**
     * @param array<string, mixed> $args
     * @return \WP_Post[]
     */
    public static function get(string $postType, array $args = []): array
    {
        $posts = get_posts(array_merge([
            'numberposts' => -1,
            'suppress_filters' => false,
        ], $args, [
            'post_type' => $postType,
            'post_status' => 'publish',
        ]));

        return array_values(array_filter(
            $posts,
            static fn ($post): bool => self::isVisible($post, $postType)
        ));
    }

    /**
     * An invalid explicit selection must never fall back to all entries.
     *
     * @param mixed $ids A single ID, comma-separated IDs, or an array of IDs.
     * @param array<string, mixed> $args
     * @return \WP_Post[]
     */
    public static function byIds(string $postType, $ids, array $args = []): array
    {
        $values = is_array($ids) ? $ids : explode(',', is_scalar($ids) ? (string) $ids : '');
        $postIds = [];
        foreach ($values as $value) {
            if (!is_scalar($value) || !preg_match('/^[0-9]+$/D', trim((string) $value))) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0) {
                $postIds[] = $id;
            }
        }

        if ($postIds === []) {
            return [];
        }

        return self::get($postType, array_merge($args, [
            'post__in' => array_values(array_unique($postIds)),
            'orderby' => 'post__in',
        ]));
    }

    /**
     * Preserve the legacy slug > ID > all selection and explicit ID order.
     *
     * @param array<string, mixed> $attributes
     * @return \WP_Post[]
     */
    public static function select(string $postType, array $attributes): array
    {
        $args = [];
        $language = sanitize_text_field((string) ($attributes['lang'] ?? ''));
        if ($language !== '') {
            $args['meta_query'] = [['key' => 'lang', 'value' => $language]];
        }

        $slug = (string) ($attributes['slug'] ?? '');
        if ($slug !== '') {
            return self::get($postType, array_merge($args, ['name' => $slug, 'numberposts' => 1]));
        }

        if (!empty($attributes['id'])) {
            return self::byIds($postType, $attributes['id'], $args);
        }

        return self::get($postType, $args);
    }
}
