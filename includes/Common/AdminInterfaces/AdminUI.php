<?php
declare(strict_types=1);

namespace RRZE\Answers\Common\AdminInterfaces;

defined('ABSPATH') || exit;

/**
 * Abstract base class for CPT admin UI.
 */
abstract class AdminUI
{
    protected string $post_type;

    /** @var array<string,mixed> */
    protected array $features;

    /** @var string[] */
    protected array $taxSlugs = [];

    public function __construct(string $post_type, array $features = [])
    {
        $this->post_type = $post_type;

        $this->features = array_merge([
            'has_taxonomies' => false,
            'default_orderby' => 'title',
            'default_order' => 'ASC',
            'sortable_meta_keys' => [],
            'sync_readonly' => true,
            'show_shortcode_box' => false,
        ], $features);

        if ($this->features['has_taxonomies']) {
            $this->taxSlugs = [
                "{$this->post_type}_category",
                "{$this->post_type}_tag",
            ];
        }

        add_filter('pre_get_posts', [$this, 'preGetPosts']);
        add_filter('enter_title_here', [$this, 'enterTitleHere'], 10, 2);
        add_action('admin_menu', [$this, 'maybeToggleEditor']);

        add_filter("manage_{$this->post_type}_posts_columns", [$this, 'columns']);
        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'columnValue'], 10, 2);
        add_filter("manage_edit-{$this->post_type}_sortable_columns", [$this, 'sortableColumns']);

        if ($this->features['has_taxonomies']) {

            add_filter("manage_edit-{$this->post_type}_category_columns", [$this, 'taxColumns']);
            add_filter("manage_{$this->post_type}_category_custom_column", [$this, 'taxColumnValue'], 10, 3);

            add_filter("manage_edit-{$this->post_type}_tag_columns", [$this, 'taxColumns']);
            add_filter("manage_{$this->post_type}_tag_custom_column", [$this, 'taxColumnValue'], 10, 3);

            add_action('restrict_manage_posts', [$this, 'renderListFilters'], 10, 1);
            add_filter('parse_query', [$this, 'applyListFilters'], 10);
        }

        add_action('add_meta_boxes', [$this, 'registerMetaboxes']);
        add_action("save_post_{$this->post_type}", [$this, 'savePostMeta']);
    }

    public function preGetPosts(\WP_Query $q): void
    {
        if (!is_admin() || !$q->is_main_query()) {
            return;
        }

        $post_type = $q->get('post_type');

        if (
            $post_type !== $this->post_type &&
            !(is_array($post_type) && in_array($this->post_type, $post_type, true))
        ) {
            return;
        }

        if (!$q->get('orderby')) {
            $q->set('orderby', $this->features['default_orderby']);
            $q->set('order', $this->features['default_order']);
        }

        $orderby = (string) $q->get('orderby');

        if (in_array($orderby, $this->features['sortable_meta_keys'], true)) {
            $q->set('meta_key', $orderby);
            $q->set('orderby', 'meta_value');
        }
    }

    public function enterTitleHere(string $title, \WP_Post $post): string
    {
        if ($post->post_type === $this->post_type) {
            return $this->titlePlaceholder();
        }
        return $title;
    }

    public function maybeToggleEditor(): void
    {
        $post_id = (int) ($_GET['post'] ?? $_POST['post_ID'] ?? 0);

        if (!$post_id || get_post_type($post_id) !== $this->post_type) {
            return;
        }

        if ($this->features['sync_readonly'] && $this->isSynced($post_id)) {
            $this->makeReadOnlyUI($post_id);
        }

        if (!function_exists('use_block_editor_for_post') || !use_block_editor_for_post($post_id)) {

            if ($this->features['show_shortcode_box']) {

                add_meta_box(
                    'shortcode_box',
                    __('Integration in pages and posts as a shortcode', 'rrze-answers'),
                    [$this, 'renderShortcodeBox'],
                    $this->post_type,
                    'normal'
                );
            }
        }
    }

    public function columns(array $cols): array
    {
        return $this->listTableColumns($cols);
    }

    public function sortableColumns(array $cols): array
    {
        return $this->listTableSortableColumns($cols);
    }

    public function columnValue(string $col, int $post_id): void
    {
        $this->renderListTableColumn($col, $post_id);
    }

    public function taxColumns(array $cols): array
    {
        return $this->taxonomyColumns($cols);
    }

    public function taxColumnValue($content, string $col, int $term_id)
    {
        $new = $this->renderTaxonomyColumn($col, $term_id);

        return $new !== null ? $new : $content;
    }

    public function renderListFilters(string $screen_post_type): void
    {
        if ($screen_post_type !== $this->post_type) {
            return;
        }

        $this->listFiltersUI();
    }

    public function applyListFilters(\WP_Query $q): \WP_Query
    {
        if (!is_admin() || !$q->is_main_query()) {
            return $q;
        }

        if (($q->query['post_type'] ?? null) !== $this->post_type) {
            return $q;
        }

        return $this->applyFiltersToQuery($q);
    }

    public function registerMetaboxes(): void
    {
        foreach ($this->metaboxes() as $box) {

            add_meta_box(
                $box['id'],
                $box['title'],
                $box['callback'],
                $this->post_type,
                $box['context'] ?? 'normal',
                $box['priority'] ?? 'default'
            );
        }
    }

    abstract protected function titlePlaceholder(): string;

    protected function isSynced(int $post_id): bool
    {
        $source = (string) get_post_meta($post_id, 'source', true);

        return $source !== '' && $source !== 'website';
    }

    protected function makeReadOnlyUI(int $post_id): void
    {
        remove_post_type_support($this->post_type, 'title');
        remove_post_type_support($this->post_type, 'editor');

        remove_meta_box("{$this->post_type}_categorydiv", $this->post_type, 'side');
        remove_meta_box("tagsdiv-{$this->post_type}_tag", $this->post_type, 'side');

        $link = $this->sourceEditLink($post_id);

        add_meta_box(
            'read_only_content_box',
            sprintf(
                '%1$s. %2$s',
                esc_html__('This item cannot be edited because it is synchronized', 'rrze-answers'),
                $link ? '<a href="' . esc_url($link) . '" target="_blank">' . esc_html__('You can edit it at the source', 'rrze-answers') . '</a>' : ''
            ),
            [$this, 'fillContentBox'],
            $this->post_type,
            'normal',
            'high'
        );
    }

    public function fillContentBox(\WP_Post $post): void
    {
        $content = apply_filters('the_content', $post->post_content);

        echo '<h1>' . esc_html($post->post_title) . '</h1><br>' . wp_kses_post($content);
    }

    public function renderShortcodeBox(): void
    {
        global $post;

        if (!$post || (int) $post->ID <= 0) {
            return;
        }

        $ret = '';

        foreach (["{$this->post_type}_category", "{$this->post_type}_tag"] as $tax) {

            $terms = wp_get_post_terms($post->ID, $tax);

            $list = implode(', ', wp_list_pluck($terms, 'slug'));

            if ($tax === "{$this->post_type}_category" && $list) {
                $ret .= "<p>[faq category=\"$list\"]</p>";
            }

            if ($tax === "{$this->post_type}_tag" && $list) {
                $ret .= "<p>[faq tag=\"$list\"]</p>";
            }
        }

        $ret .= '<p>[faq id="' . (int) $post->ID . '"]</p>';
        $ret .= '<p>[faq]</p>';

        echo wp_kses_post($ret);
    }

    protected function listTableColumns(array $cols): array
    {
        return $cols;
    }

    protected function listTableSortableColumns(array $cols): array
    {
        return $cols;
    }

    protected function renderListTableColumn(string $col, int $post_id): void
    {
    }

    protected function taxonomyColumns(array $cols): array
    {
        return $cols;
    }

    protected function renderTaxonomyColumn(string $col, int $term_id): ?string
    {
        return null;
    }

    protected function listFiltersUI(): void
    {
        foreach ($this->taxSlugs as $slug) {

            $taxonomy = get_taxonomy($slug);

            if (!$taxonomy) {
                continue;
            }

            $selected = isset($_GET[$slug])
                ? sanitize_text_field(wp_unslash((string) $_GET[$slug]))
                : '';

            wp_dropdown_categories([
                'show_option_all' => $taxonomy->labels->all_items,
                'taxonomy' => $slug,
                'name' => $slug,
                'orderby' => 'name',
                'value_field' => 'slug',
                'selected' => $selected,
                'hierarchical' => true,
                'hide_empty' => true,
                'show_count' => true,
            ]);
        }
    }

    protected function applyFiltersToQuery(\WP_Query $q): \WP_Query
    {
        $tax_query = [];

        foreach ($this->taxSlugs as $slug) {

            $val = isset($_GET[$slug])
                ? sanitize_text_field(wp_unslash((string) $_GET[$slug]))
                : '';

            if ($val !== '') {

                $tax_query[] = [
                    'taxonomy' => $slug,
                    'field' => 'slug',
                    'terms' => $val,
                ];
            }
        }

        if ($tax_query) {
            $q->set('tax_query', $tax_query);
        }

        return $q;
    }

    protected function metaboxes(): array
    {
        return [];
    }

    public function savePostMeta(int $post_id): void
    {
    }

    protected function sourceEditLink(int $post_id): ?string
    {
        $source = (string) get_post_meta($post_id, 'source', true);
        $remoteID = (string) get_post_meta($post_id, 'remoteID', true);

        if ($source === '' || $source === 'website' || $remoteID === '') {
            return null;
        }

        $domains = [];

        if (class_exists('\\RRZE\\Answers\\Common\\API\\SyncAPI\\SyncAPI')) {

            $api = new \RRZE\Answers\Common\API\SyncAPI\SyncAPI();

            if (method_exists($api, 'getDomains')) {
                $domains = (array) $api->getDomains();
            }
        }

        if (!empty($domains[$source])) {

            return rtrim((string) $domains[$source], '/')
                . '/wp-admin/post.php?post='
                . urlencode($remoteID)
                . '&action=edit';
        }

        return null;
    }
}