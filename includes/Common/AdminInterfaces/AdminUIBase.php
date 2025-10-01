<?php
declare(strict_types=1);

namespace RRZE\Answers\Common\AdminInterfaces;

defined('ABSPATH') || exit;

/**
 * Abstract base class for CPT admin UI.
 *
 * Provides:
 * - List table columns/sorting hooks
 * - Metabox registration
 * - Optional taxonomy list-table columns/filters
 * - Read-only handling for synced content
 * - Safe extension points for subclasses
 */
abstract class AdminUIBase
{
    /** @var string */
    protected string $post_type;

    /** @var array<string, mixed> */
    protected array $features;

    /** @var string[] */
    protected array $taxSlugs = [];

    /**
     * @param string $post_type  CPT slug (e.g. 'rrze_faq')
     * @param array  $features   Feature flags & defaults
     */
    public function __construct(string $post_type, array $features = [])
    {
        $this->post_type = $post_type;
        $this->features = array_merge([
            'has_taxonomies'     => false,          // *_category / *_tag
            'default_orderby'    => 'title',
            'default_order'      => 'ASC',
            'sortable_meta_keys' => [],             // e.g. ['sortfield']
            'sync_readonly'      => true,           // make synced items read-only
            'show_shortcode_box' => false,          // render shortcode box in classic editor
        ], $features);

        if ($this->features['has_taxonomies']) {
            $this->taxSlugs = [
                "{$this->post_type}_category",
                "{$this->post_type}_tag",
            ];
        }

        // Query defaults/sorting
        add_filter('pre_get_posts', [$this, 'preGetPosts']);

        // Title placeholder
        add_filter('enter_title_here', [$this, 'enterTitleHere'], 10, 2);

        // Read-only editor toggle & optional shortcode box
        add_action('admin_menu', [$this, 'maybeToggleEditor']);

        // Post list table columns
        add_filter("manage_{$this->post_type}_posts_columns", [$this, 'columns']);
        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'columnValue'], 10, 2);
        add_filter("manage_edit-{$this->post_type}_sortable_columns", [$this, 'sortableColumns']);

        // Taxonomy list-table columns (category/tag), if enabled
        if ($this->features['has_taxonomies']) {
            add_filter("manage_edit-{$this->post_type}_category_columns", [$this, 'taxColumns']);
            add_action("manage_{$this->post_type}_category_custom_column", [$this, 'taxColumnValue'], 10, 3);

            add_filter("manage_edit-{$this->post_type}_tag_columns", [$this, 'taxColumns']);
            add_action("manage_{$this->post_type}_tag_custom_column", [$this, 'taxColumnValue'], 10, 3);

            // Filters above the post list & parsing the request
            add_action('restrict_manage_posts', [$this, 'renderListFilters'], 10, 1);
            add_filter('parse_query', [$this, 'applyListFilters'], 10);
        }

        // Metaboxes and saving
        add_action('add_meta_boxes', [$this, 'registerMetaboxes']);
        add_action("save_post_{$this->post_type}", [$this, 'savePostMeta']);
    }

    /* -----------------------------------------------------------------
     * Core hooks (mostly stable, subclasses rarely need to override)
     * ----------------------------------------------------------------- */

    public function preGetPosts(\WP_Query $q): void
    {
        if (!is_admin() || empty($q->query['post_type']) || $q->get('post_type') !== $this->post_type) {
            return;
        }

        // Default ordering for this CPT
        if (!$q->get('orderby')) {
            $q->set('orderby', $this->features['default_orderby']);
            $q->set('order', $this->features['default_order']);
        }

        // If sorting by a known meta key, set proper meta_query/orderby
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

        // Turn synced posts into read-only UI, if feature enabled
        if ($this->features['sync_readonly'] && $this->isSynced($post_id)) {
            $this->makeReadOnlyUI($post_id);
        }

        // Classic editor: optional shortcode box
        if (!function_exists('use_block_editor_for_post') || !use_block_editor_for_post($post_id)) {
            if ($this->features['show_shortcode_box']) {
                add_meta_box(
                    'shortcode_box',
                    __('Integration in pages and posts', 'rrze-answers'),
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
        $this->renderTaxonomyColumn($col, $term_id);
        return $content;
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
        if (!(is_admin() && $q->is_main_query())) {
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

    /* -----------------------------------------------------------------
     * Template methods for subclasses
     * ----------------------------------------------------------------- */

    /** Placeholder shown in the title field. */
    abstract protected function titlePlaceholder(): string;

    /** Whether the post is synced (and thus should be read-only). */
    protected function isSynced(int $post_id): bool
    {
        $source = (string) get_post_meta($post_id, 'source', true);
        return $source !== '' && $source !== 'website';
    }

    /**
     * Converts the edit screen into a read-only UI for synced items.
     * Subclasses may override to tweak which boxes to remove.
     */
    protected function makeReadOnlyUI(int $post_id): void
    {
        // Remove main editing supports for this post type
        remove_post_type_support($this->post_type, 'title');
        remove_post_type_support($this->post_type, 'editor');

        // Remove default taxonomy boxes if present
        remove_meta_box("{$this->post_type}_categorydiv", $this->post_type, 'side');
        remove_meta_box("tagsdiv-{$this->post_type}_tag", $this->post_type, 'side');

        // Link to source edit screen, if possible
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

    /** Renders the content preview for read-only items. */
    public function fillContentBox(\WP_Post $post): void
    {
        $content = apply_filters('the_content', $post->post_content);
        echo '<h1>' . esc_html($post->post_title) . '</h1><br>' . wp_kses_post($content);
    }

    /** Optional: renders a shortcode helper box (classic editor only). */
    protected function renderShortcodeBox(): void
    {
        // Subclasses may implement to show helpful shortcodes for this CPT
    }

    /** Columns for the post list table. */
    protected function listTableColumns(array $cols): array { return $cols; }

    /** Sortable columns for the post list table. */
    protected function listTableSortableColumns(array $cols): array { return $cols; }

    /** Render values for custom columns. */
    protected function renderListTableColumn(string $col, int $post_id): void {}

    /** Taxonomy list-table columns. */
    protected function taxonomyColumns(array $cols): array { return $cols; }

    /** Render values for taxonomy list-table custom columns. */
    protected function renderTaxonomyColumn(string $col, int $term_id): void {}

    /** Render filter UI above list table (categories/tags + source). */
    protected function listFiltersUI(): void
    {
        // Default implementation: category/tag dropdowns + "Source" meta filter
        foreach ($this->taxSlugs as $slug) {
            $taxonomy = get_taxonomy($slug);
            if (!$taxonomy) {
                continue;
            }

            $selected = isset($_GET[$slug]) ? sanitize_text_field(wp_unslash((string) $_GET[$slug])) : '';
            wp_dropdown_categories([
                'show_option_all' => $taxonomy->labels->all_items,
                'taxonomy'        => $slug,
                'name'            => $slug,
                'orderby'         => 'name',
                'value_field'     => 'slug',   // store slug (we’ll handle in parse_query)
                'selected'        => $selected,
                'hierarchical'    => true,
                'hide_empty'      => true,
                'show_count'      => true,
            ]);
        }

        // Build "Source" dropdown from existing posts' meta
        $selectedVal = isset($_GET['source']) ? sanitize_text_field(wp_unslash((string) $_GET['source'])) : '';

        $posts = get_posts([
            'post_type'   => $this->post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
            'meta_key'    => 'source',
            'orderby'     => 'meta_value',
        ]);

        $sources = [];
        foreach ($posts as $pid) {
            $val = get_post_meta((int) $pid, 'source', true);
            if ($val !== '') {
                $sources[] = (string) $val;
            }
        }

        $sources = array_values(array_unique($sources, SORT_STRING));
        sort($sources, SORT_NATURAL | SORT_FLAG_CASE);

        if (count($sources) > 1) {
            echo "<select name='source'>";
            echo '<option value="">' . esc_html__('All Sources', 'rrze-answers') . '</option>';
            foreach ($sources as $term) {
                $sel = ($term === $selectedVal) ? 'selected' : '';
                echo "<option value='" . esc_attr($term) . "' $sel>" . esc_html($term) . "</option>";
            }
            echo '</select>';
        }
    }

    /** Apply GET-based filters to the main query (taxonomies + source meta). */
    protected function applyFiltersToQuery(\WP_Query $q): \WP_Query
    {
        $tax_query = [];

        foreach ($this->taxSlugs as $slug) {
            $val = isset($_GET[$slug]) ? sanitize_text_field(wp_unslash((string) $_GET[$slug])) : '';
            if ($val !== '') {
                $tax_query[] = [
                    'taxonomy' => $slug,
                    'field'    => 'slug',
                    'terms'    => $val,
                ];
            }
        }

        if (!empty($tax_query)) {
            $q->query_vars['tax_query'] = $tax_query;
        }

        $source = isset($_GET['source']) ? sanitize_text_field(wp_unslash((string) $_GET['source'])) : '';
        if ($source !== '') {
            $q->query_vars['meta_query'] = [[
                'key'     => 'source',
                'value'   => $source,
                'compare' => '=',
            ]];
        }

        return $q;
    }

    /** Metabox definitions for this CPT. */
    protected function metaboxes(): array { return []; }

    /**
     * Save post meta (subclasses MUST implement nonce + capability checks).
     * @param int $post_id
     */
    public function savePostMeta(int $post_id): void {}

    /* -----------------------------------------------------------------
     * Utilities
     * ----------------------------------------------------------------- */

    /**
     * Builds a link to the source edit screen based on SyncAPI domains.
     * Tries both class names to be robust with different plugin layouts.
     */
    protected function sourceEditLink(int $post_id): ?string
    {
        $source    = (string) get_post_meta($post_id, 'source', true);
        $remoteID  = (string) get_post_meta($post_id, 'remoteID', true);
        if ($source === '' || $source === 'website' || $remoteID === '') {
            return null;
        }

        // Try both possible SyncAPI class names
        $domains = [];
        if (class_exists('\\RRZE\\Answers\\Common\\API\\SyncAPI\\SyncAPI')) {
            $api = new \RRZE\Answers\Common\API\SyncAPI\SyncAPI();
            if (method_exists($api, 'getDomains')) {
                $domains = (array) $api->getDomains();
            }
        } elseif (class_exists('\\RRZE\\Answers\\Common\\API\\SyncAPI')) {
            $api = new \RRZE\Answers\Common\API\SyncAPI();
            if (method_exists($api, 'getDomains')) {
                $domains = (array) $api->getDomains();
            }
        }

        if (!empty($domains[$source])) {
            return rtrim((string) $domains[$source], '/') . '/wp-admin/post.php?post=' . urlencode($remoteID) . '&action=edit';
        }
        return null;
    }
}
