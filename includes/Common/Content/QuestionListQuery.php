<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Content;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Tools;

/**
 * Select a question list using its explicit IDs or combined taxonomy/meta filters.
 */
final class QuestionListQuery
{
    /** @return \WP_Post[] */
    public static function select(string $type, array $attributes): array
    {
        $postType = 'rrze_' . $type;
        if (!empty($attributes['id'])) {
            return EntryQuery::byIds($postType, $attributes['id']);
        }

        $query = [];
        if ($attributes['sort'] === 'sortfield') {
            $query['orderby'] = ['meta_value' => $attributes['order'], 'title' => $attributes['order']];
            $query['meta_key'] = 'sortfield';
        } else {
            $query['orderby'] = $attributes['sort'];
            $query['order'] = $attributes['order'];
        }

        $taxonomies = array_filter([
            $postType . '_category' => Tools::getTaxBySource($attributes['category']),
            $postType . '_tag' => Tools::getTaxBySource($attributes['tag']),
        ]);
        if ($taxonomies) {
            $taxQuery = Tools::getTaxQuery($taxonomies);
            if ($taxQuery) {
                $query['tax_query'] = $taxQuery;
            }
        }

        $metaQuery = [];
        $language = trim($attributes['lang']);
        if ($language) {
            $metaQuery[] = ['key' => 'lang', 'value' => $language, 'compare' => '='];
        }
        $sources = array_filter(array_map('trim', explode(',', $attributes['domain'] ?? '')));
        if ($sources) {
            $metaQuery[] = ['key' => 'source', 'value' => $sources, 'compare' => 'IN'];
        }
        if ($metaQuery) {
            $query['meta_query'] = array_merge(['relation' => 'AND'], $metaQuery);
        }

        return EntryQuery::get($postType, $query);
    }
}
