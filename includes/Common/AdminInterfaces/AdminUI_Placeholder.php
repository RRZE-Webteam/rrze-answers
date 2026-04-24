<?php
declare(strict_types=1);

namespace RRZE\Answers\Common\AdminInterfaces;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Tools;

class AdminUI_Placeholder extends AdminUI
{
    /** @var array<string,string> */
    protected array $langChoices = [];
    private bool $metaNoncePrinted = false;

    public function __construct()
    {
        parent::__construct('rrze_placeholder', [
            'has_taxonomies' => false,
            'default_orderby' => 'title',
            'default_order' => 'ASC',
            'sortable_meta_keys' => [],
            'sync_readonly' => true,
            'show_shortcode_box' => true,
        ]);

        // Provide language choices (try Defaults, fallback to common choices)
        $this->langChoices = $this->loadLanguageChoices();
    }

    protected function get_title(): string
    {
        return __('Enter placeholder here', 'rrze-answers');
    }

    /* ---------------- Metaboxes ---------------- */

    protected function metaboxes(): array
    {
        return [
            [
                'id' => 'langbox',
                'title' => __('Language', 'rrze-answers'),
                'callback' => [$this, 'langboxCallback'],
                'context' => 'side',
            ],
        ];
    }

    public function langboxCallback(\WP_Post $post): void
    {
        if (!$this->metaNoncePrinted) {
            wp_nonce_field('rrze_placeholder_save_meta', 'rrze_placeholder_meta_nonce');
            $this->metaNoncePrinted = true;
        }

        $current = (string) get_post_meta($post->ID, 'lang', true);
        if ($current === '') {
            $current = substr(get_locale(), 0, 2);
        }

        echo '<select name="lang" id="lang" class="lang">';
        foreach ($this->langChoices as $code => $desc) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($current, $code, false) . '>' . esc_html($desc) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Language of this placeholder', 'rrze-answers') . '</p>';
    }

    // public function renderShortcodeBox(): void
    // {
    //     global $post;
    //     if (!$post || (int) $post->ID <= 0) {
    //         return;
    //     }

    //     $ret = '';
    //     $ret .= '<p>[placeholder id="' . (int) $post->ID . '"]</p>';
    //     if ($post->post_name) {
    //         $ret .= '<p>[placeholder slug="' . esc_html($post->post_name) . '"]</p>';
    //     }
    //     $ret .= '<p>[fau_abbr id="' . (int) $post->ID . '"]</p>';
    //     if ($post->post_name) {
    //         $ret .= '<p>[fau_abbr slug="' . esc_html($post->post_name) . '"]</p>';
    //     }
    //     echo wp_kses_post($ret);
    // }

    /* ---------------- Read-only UI for synced placeholders ---------------- */

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
                esc_html__('This placeholder cannot be edited because it is synchronized', 'rrze-answers'),
                $link ? '<a href="' . esc_url($link) . '" target="_blank">' . esc_html__('You can edit it at the source', 'rrze-answers') . '</a>' : ''
            ),
            [$this, 'fillContentBoxplaceholder'],
            $this->post_type,
            'normal',
            'high'
        );
    }

    public function fillContentBoxplaceholder(\WP_Post $post): void
    {
        $placeholder = (string) get_post_meta($post->ID, 'placeholder', true);
        $titleLang = (string) get_post_meta($post->ID, 'titleLang', true);
        $langLabel = $this->langChoices[$titleLang] ?? $titleLang;

        echo '<h1>' . esc_html($post->post_title) . '</h1><br>';
        echo '<strong>' . esc_html__('Full form', 'rrze-answers') . ':</strong>';
        echo '<p>' . esc_html($placeholder) . '</p>';
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

        if (!isset($_POST['rrze_placeholder_meta_nonce']) || !wp_verify_nonce(wp_unslash((string) $_POST['rrze_placeholder_meta_nonce']), 'rrze_placeholder_save_meta')) {
            return;
        }

        update_post_meta($post_id, 'source', 'website'); // placeholders are authored locally by default
        update_post_meta($post_id, 'lang', substr(get_locale(), 0, 2));
        update_post_meta($post_id, 'remoteID', $post_id);
        if (isset($_POST['lang']) && $_POST['lang'] !== '') {
            update_post_meta($post_id, 'lang', sanitize_text_field(wp_unslash((string) $_POST['lang'])));
        }

        update_post_meta($post_id, 'remoteChanged', get_post_timestamp($post_id, 'modified'));
    }

    /* ---------------- List table: posts ---------------- */

    protected function listTableColumns(array $cols): array
    {
        $cols['title'] = __('Placeholder', 'rrze-answers');
        $cols['lang'] = __('Language', 'rrze-answers');

        if ((new Tools())->hasSync('rrze_placeholder')) {
            $cols['source'] = __('Source', 'rrze-answers');
        }

        return $cols;
    }

    protected function listTableSortableColumns(array $cols): array
    {
        $cols['lang'] = __('Language', 'rrze-answers');
        $cols['source'] = __('Source', 'rrze-answers');
        return $cols;
    }

    protected function renderListTableColumn(string $col, int $post_id): void
    {
        if ($col === 'id') {
            echo (int) $post_id;
        } elseif ($col === 'lang') {
            echo esc_html((string) get_post_meta($post_id, 'lang', true));
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
