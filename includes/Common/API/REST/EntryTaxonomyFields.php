<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\API\REST;

defined('ABSPATH') || exit;

/**
 * Exposes synchronized entry taxonomy names without shadowing core fields.
 *
 * WordPress already exposes each REST-enabled taxonomy under its taxonomy
 * name, using source-site term IDs. These additional fields deliberately use
 * plugin-specific names so block-editor writes keep using the native fields.
 */
final class EntryTaxonomyFields
{
    public const CATEGORY_NAMES_FIELD = 'rrze_answers_category_names';

    public const TAG_NAMES_FIELD = 'rrze_answers_tag_names';

    /** @var array<string, array{category: string, tag: string}> */
    private const TAXONOMIES_BY_POST_TYPE = [
        'rrze_faq' => [
            'category' => 'rrze_faq_category',
            'tag' => 'rrze_faq_tag',
        ],
        'rrze_glossary' => [
            'category' => 'rrze_glossary_category',
            'tag' => 'rrze_glossary_tag',
        ],
    ];

    /**
     * Register read-only term-name fields for synchronized post types.
     */
    public function register(): void
    {
        foreach (array_keys(self::TAXONOMIES_BY_POST_TYPE) as $postType) {
            register_rest_field($postType, self::CATEGORY_NAMES_FIELD, [
                'get_callback' => [$this, 'getCategoryNames'],
                'schema' => $this->getFieldSchema(
                    __('Category names used for Answers synchronization.', 'rrze-answers')
                ),
            ]);
            register_rest_field($postType, self::TAG_NAMES_FIELD, [
                'get_callback' => [$this, 'getTagNames'],
                'schema' => $this->getFieldSchema(
                    __('Tag names used for Answers synchronization.', 'rrze-answers')
                ),
            ]);
        }
    }

    /**
     * Return category names assigned to a REST entry.
     *
     * @param array<string, mixed> $object Prepared WordPress REST object.
     * @return string[]|\WP_Error
     */
    public function getCategoryNames(array $object)
    {
        return $this->getTermNames($object, 'category');
    }

    /**
     * Return tag names assigned to a REST entry.
     *
     * @param array<string, mixed> $object Prepared WordPress REST object.
     * @return string[]|\WP_Error
     */
    public function getTagNames(array $object)
    {
        return $this->getTermNames($object, 'tag');
    }

    /**
     * Resolve the correct taxonomy from the REST object's post type.
     *
     * @param array<string, mixed> $object
     * @param 'category'|'tag'     $termType
     * @return string[]|\WP_Error
     */
    private function getTermNames(array $object, string $termType)
    {
        $postType = isset($object['type']) ? (string) $object['type'] : '';
        $postId = isset($object['id']) ? (int) $object['id'] : 0;
        $taxonomy = self::TAXONOMIES_BY_POST_TYPE[$postType][$termType] ?? null;

        if ($postId <= 0 || $taxonomy === null) {
            return [];
        }

        $termNames = wp_get_post_terms($postId, $taxonomy, ['fields' => 'names']);

        if (is_wp_error($termNames)) {
            return $termNames;
        }

        return array_map('strval', $termNames);
    }

    /**
     * Build the shared read-only REST schema for a term-name list.
     *
     * @return array<string, mixed>
     */
    private function getFieldSchema(string $description): array
    {
        return [
            'description' => $description,
            'type' => 'array',
            'items' => ['type' => 'string'],
            'context' => ['view', 'edit'],
            'readonly' => true,
        ];
    }
}
