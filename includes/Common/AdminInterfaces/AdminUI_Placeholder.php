<?php
declare(strict_types=1);

namespace RRZE\Answers\Common\AdminInterfaces;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Tools;

class AdminUI_Placeholder extends AdminUIBase
{
    /** @var array<string,string> */
    protected array $langChoices = [];

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

    protected function titlePlaceholder(): string
    {
        return __('Enter placeholder here', 'rrze-answers');
    }

    /* ---------------- Metaboxes ---------------- */

    protected function metaboxes(): array
    {
        return [
            [
                'id' => 'postmetabox',
                'title' => __('Properties', 'rrze-answers'),
                'callback' => [$this, 'postmetaCallback'],
                'context' => 'normal',
                'priority' => 'high',
            ],
        ];
    }

    public function postmetaCallback(\WP_Post $post): void
    {
        // Nonce
        wp_nonce_field('rrze_placeholder_save_meta', 'rrze_placeholder_meta_nonce');

        $source = (string) get_post_meta($post->ID, 'source', true);
        $placeholder = (string) get_post_meta($post->ID, 'placeholder', true);
        $titleLang = (string) get_post_meta($post->ID, 'titleLang', true);

        // Properties
        echo '<p><label for="placeholder">' . esc_html__('Full form', 'rrze-answers') . '</label></p>';
        echo '<textarea rows="3" cols="60" name="placeholder" id="placeholder">' . esc_textarea($placeholder) . '</textarea>';
        echo '<p class="description">' . esc_html__('Enter the long, written form of the placeholder. This text replaces the shortcode. Note: line breaks or HTML are not accepted.', 'rrze-answers') . '</p>';

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
            [$this, 'fillContentBoxPlaceholder'],
            $this->post_type,
            'normal',
            'high'
        );
    }

    public function fillContentBoxPlaceholder(\WP_Post $post): void
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
        update_post_meta($post_id, 'remoteID', $post_id);

        if (isset($_POST['placeholder'])) {
            update_post_meta($post_id, 'placeholder', sanitize_text_field(wp_unslash((string) $_POST['placeholder'])));
        }
        if (isset($_POST['titleLang'])) {
            update_post_meta($post_id, 'titleLang', sanitize_text_field(wp_unslash((string) $_POST['titleLang'])));
        }

        update_post_meta($post_id, 'remoteChanged', get_post_timestamp($post_id, 'modified'));
    }

    /* ---------------- List table: posts ---------------- */

    protected function listTableColumns(array $cols): array
    {
        $cols['title'] = __('Placeholder', 'rrze-answers');

        if ((new Tools())->hasSync('placeholder')) {
            $cols['source'] = __('Source', 'rrze-answers');
        }

        return $cols;
    }

    protected function listTableSortableColumns(array $cols): array
    {
        $cols['source'] = __('Source', 'rrze-answers');
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
