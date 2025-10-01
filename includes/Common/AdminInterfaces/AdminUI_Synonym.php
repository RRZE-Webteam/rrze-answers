<?php
declare(strict_types=1);

namespace RRZE\Answers\Common\AdminInterfaces;

defined('ABSPATH') || exit;

/**
 * Admin UI for rrze_synonym.
 * - Custom properties metabox (synonym, titleLang)
 * - Read-only mode for synced items
 * - Shortcode helper box
 * - Custom list table columns (Source, ID)
 */
class AdminUI_Synonym extends AdminUIBase
{
    /** @var array<string,string> */
    protected array $langChoices = [];

    public function __construct()
    {
        parent::__construct('rrze_synonym', [
            'has_taxonomies'     => false,
            'default_orderby'    => 'title',
            'default_order'      => 'ASC',
            'sortable_meta_keys' => [],
            'sync_readonly'      => true,
            'show_shortcode_box' => true,
        ]);

        // Provide language choices (try Defaults, fallback to common choices)
        $this->langChoices = $this->loadLanguageChoices();
    }

    protected function titlePlaceholder(): string
    {
        return __('Enter synonym here', 'rrze-answers');
    }

    /* ---------------- Metaboxes ---------------- */

    protected function metaboxes(): array
    {
        return [
            [
                'id'       => 'postmetabox',
                'title'    => __('Properties', 'rrze-answers'),
                'callback' => [$this, 'postmetaCallback'],
                'context'  => 'normal',
                'priority' => 'high',
            ],
        ];
    }

    public function postmetaCallback(\WP_Post $post): void
    {
        // Nonce
        wp_nonce_field('rrze_synonym_save_meta', 'rrze_synonym_meta_nonce');

        $source    = (string) get_post_meta($post->ID, 'source', true);
        $synonym   = (string) get_post_meta($post->ID, 'synonym', true);
        $titleLang = (string) get_post_meta($post->ID, 'titleLang', true);

        // Properties
        echo '<p><label for="synonym">' . esc_html__('Full form', 'rrze-answers') . '</label></p>';
        echo '<textarea rows="3" cols="60" name="synonym" id="synonym">' . esc_textarea($synonym) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter the long, written form of the synonym. This text replaces the shortcode. Note: line breaks or HTML are not accepted.', 'rrze-answers') . '</p>';

        // Language dropdown
        $selectedLang = $titleLang !== '' ? $titleLang : substr(get_locale(), 0, 2);
        echo '<br><label for="titleLang">' . esc_html__('Pronunciation language', 'rrze-answers') . '</label>';
        echo '<select id="titleLang" name="titleLang">';
        foreach ($this->langChoices as $code => $desc) {
            $sel = ($code === $selectedLang) ? ' selected' : '';
            echo '<option value="' . esc_attr($code) . '"' . $sel . '>' . esc_html($desc) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Choose the language in which the long form is pronounced.', 'rrze-answers') . '</p>';

        // Keep source (hidden)
        if ($source === '') {
            $source = 'website';
        }
        echo '<input type="hidden" name="source" id="source" value="' . esc_attr($source) . '">';
    }

    protected function renderShortcodeBox(): void
    {
        global $post;
        if (!$post || (int) $post->ID <= 0) {
            return;
        }

        $ret = '';
        $ret .= '<p>[synonym id="' . (int) $post->ID . '"]</p>';
        if ($post->post_name) {
            $ret .= '<p>[synonym slug="' . esc_html($post->post_name) . '"]</p>';
        }
        $ret .= '<p>[fau_abbr id="' . (int) $post->ID . '"]</p>';
        if ($post->post_name) {
            $ret .= '<p>[fau_abbr slug="' . esc_html($post->post_name) . '"]</p>';
        }
        echo wp_kses_post($ret);
    }

    /* ---------------- Read-only UI for synced synonyms ---------------- */

    protected function makeReadOnlyUI(int $post_id): void
    {
        // Remove title input and submit box for synced items
        remove_post_type_support($this->post_type, 'title');
        remove_meta_box('submitdiv', $this->post_type, 'side');

        $link = $this->sourceEditLink($post_id);

        add_meta_box(
            'read_only_content_box',
            sprintf(
                '%1$s. %2$s',
                esc_html__('This synonym cannot be edited because it is synchronized', 'rrze-answers'),
                $link ? '<a href="' . esc_url($link) . '" target="_blank">' . esc_html__('You can edit it at the source', 'rrze-answers') . '</a>' : ''
            ),
            [$this, 'fillContentBoxSynonym'],
            $this->post_type,
            'normal',
            'high'
        );
    }

    public function fillContentBoxSynonym(\WP_Post $post): void
    {
        $synonym   = (string) get_post_meta($post->ID, 'synonym', true);
        $titleLang = (string) get_post_meta($post->ID, 'titleLang', true);
        $langLabel = $this->langChoices[$titleLang] ?? $titleLang;

        echo '<h1>' . esc_html($post->post_title) . '</h1><br>';
        echo '<strong>' . esc_html__('Full form', 'rrze-answers') . ':</strong>';
        echo '<p>' . esc_html($synonym) . '</p>';
        if ($langLabel) {
            echo '<p><i>' . esc_html__('Pronunciation', 'rrze-answers') . ': ' . esc_html($langLabel) . '</i></p>';
        }
    }

    /* ---------------- Save meta ---------------- */

    public function savePostMeta(int $post_id): void
    {
        if (!current_user_can('edit_post', $post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        if (!isset($_POST['rrze_synonym_meta_nonce']) || !wp_verify_nonce(wp_unslash((string) $_POST['rrze_synonym_meta_nonce']), 'rrze_synonym_save_meta')) {
            return;
        }

        update_post_meta($post_id, 'source', 'website'); // synonyms are authored locally by default
        update_post_meta($post_id, 'remoteID', $post_id);

        if (isset($_POST['synonym'])) {
            update_post_meta($post_id, 'synonym', sanitize_text_field(wp_unslash((string) $_POST['synonym'])));
        }
        if (isset($_POST['titleLang'])) {
            update_post_meta($post_id, 'titleLang', sanitize_text_field(wp_unslash((string) $_POST['titleLang'])));
        }

        update_post_meta($post_id, 'remoteChanged', get_post_timestamp($post_id, 'modified'));
    }

    /* ---------------- List table: posts ---------------- */

    protected function listTableColumns(array $cols): array
    {
        $cols['source'] = __('Source', 'rrze-answers');
        $cols['id']     = __('ID', 'rrze-answers');
        return $cols;
    }

    protected function listTableSortableColumns(array $cols): array
    {
        $cols['source'] = __('Source', 'rrze-answers');
        $cols['id']     = __('ID', 'rrze-answers');
        return $cols;
    }

    protected function renderListTableColumn(string $col, int $post_id): void
    {
        if ($col === 'id') {
            echo (int) $post_id;
        } elseif ($col === 'source') {
            echo esc_html((string) get_post_meta($post_id, 'source', true));
        }
    }

    /* ---------------- Helpers ---------------- */

    /**
     * Try to load languages from Defaults; otherwise, fallback to a small map.
     * @return array<string,string>
     */
    protected function loadLanguageChoices(): array
    {
        // Try RRZE\Answers\Defaults::get('lang') if available
        if (class_exists('\\RRZE\\Answers\\Defaults')) {
            $defaults = new \RRZE\Answers\Defaults();
            if (method_exists($defaults, 'get')) {
                $langs = $defaults->get('lang');
                if (is_array($langs) && !empty($langs)) {
                    /** @var array<string,string> $langs */
                    return $langs;
                }
            }
        }

        // Fallback list (extend as needed)
        return [
            'de' => 'German',
            'en' => 'English',
            'fr' => 'French',
            'it' => 'Italian',
            'es' => 'Spanish',
            'nl' => 'Dutch',
            'sv' => 'Swedish',
            'pl' => 'Polish',
            'cs' => 'Czech',
            'ru' => 'Russian',
        ];
    }
}
