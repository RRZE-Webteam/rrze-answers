<?php
namespace RRZE\Answers\Common\AdminInterfaces;

defined('ABSPATH') || exit;

class AdminMenu {
    // CPT-Slugs
    private $faq_pt       = 'rrze_faq';
    private $glossary_pt  = 'rrze_glossary';
    private $placeholder_pt   = 'rrze_placeholder';

    private $faq_cat      = 'rrze_faq_category';
    private $faq_tag      = 'rrze_faq_tag';

    private $glossary_cat = 'rrze_glossary_category';
    private $glossary_tag = 'rrze_glossary_tag';

    private $syn_group    = 'rrze_placeholder_group';
    private $syn_tag      = 'rrze_placeholder_tag';

    private $parent_slug  = 'rrze-answers';

    public function __construct() {
        add_filter('register_post_type_args', [$this, 'hideCptMenus'], 20, 2);

        add_action('admin_menu', [$this, 'registerMenus'], 9);

        add_filter('parent_file',  [$this, 'fixParentHighlight']);
        add_filter('submenu_file', [$this, 'fixSubmenuHighlight']);
    }

    public function hideCptMenus(array $args, string $post_type): array {
        $targets = [$this->faq_pt, $this->glossary_pt, $this->placeholder_pt];
        if (in_array($post_type, $targets, true)) {
            $args['show_in_menu']       = false;
            $args['show_in_admin_bar']  = false;
        }
        return $args;
    }

    public function registerMenus(): void {
        add_menu_page(
            __('Answers', 'rrze-answers'),
            __('Answers', 'rrze-answers'),
            'edit_posts',
            $this->parent_slug,
            [$this, 'renderAnswersDashboard'],
            'dashicons-editor-help',
            25
        );

        add_submenu_page($this->parent_slug, __('FAQ', 'rrze-answers'), __('FAQ', 'rrze-answers'), 'edit_posts', 'rrze-answers_faq',      function () { $this->renderHub($this->faq_pt, $this->faq_cat, $this->faq_tag, __('FAQ', 'rrze-answers')); });
        add_submenu_page($this->parent_slug, __('Glossary', 'rrze-answers'), __('Glossary', 'rrze-answers'), 'edit_posts', 'rrze-answers_glossary', function () { $this->renderHub($this->glossary_pt, $this->glossary_cat, $this->glossary_tag, __('Glossary', 'rrze-answers')); });
        add_submenu_page($this->parent_slug, __('Placeholder', 'rrze-answers'), __('Placeholder', 'rrze-answers'), 'edit_posts', 'rrze-answers_placeholder', function () { $this->renderHub($this->placeholder_pt, $this->syn_group, $this->syn_tag, __('Placeholder', 'rrze-answers')); });
    }

    public function renderAnswersDashboard(): void {
        echo '<div class="wrap"><h1>'.esc_html__('Answers', 'rrze-answers').'</h1>';
        echo '<p>'.esc_html__('Wähle einen Bereich:', 'rrze-answers').'</p>';
        echo '<ul style="display:grid;gap:.5rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));max-width:900px">';
        $cards = [
            ['slug'=>'rrze-answers_faq',      'title'=>__('FAQ', 'rrze-answers'),      'desc'=>__('Fragen & Antworten verwalten', 'rrze-answers')],
            ['slug'=>'rrze-answers_glossary', 'title'=>__('Glossary', 'rrze-answers'), 'desc'=>__('Glossarbegriffe verwalten', 'rrze-answers')],
            ['slug'=>'rrze-answers_placeholder',  'title'=>__('Placeholder', 'rrze-answers'),  'desc'=>__('Placeholdere & Gruppen', 'rrze-answers')],
        ];
        foreach ($cards as $c) {
            printf(
                '<li style="border:1px solid #dadada;border-radius:8px;padding:12px;background:#fff"><h2 style="margin:.2em 0">%s</h2><p style="margin:.3em 0 1em;color:#555">%s</p><a class="button button-primary" href="%s">%s</a></li>',
                esc_html($c['title']),
                esc_html($c['desc']),
                esc_url(admin_url('admin.php?page='.$c['slug'])),
                esc_html__('Öffnen', 'rrze-answers')
            );
        }
        echo '</ul></div>';
    }

    private function renderHub(string $post_type, string $tax_cat, string $tax_tag, string $title): void {
        $all_url  = admin_url('edit.php?post_type=' . $post_type);
        $add_url  = admin_url('post-new.php?post_type=' . $post_type);
        $cat_url  = admin_url('edit-tags.php?taxonomy=' . $tax_cat . '&post_type=' . $post_type);
        $tag_url  = admin_url('edit-tags.php?taxonomy=' . $tax_tag . '&post_type=' . $post_type);

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html($title));
        echo '<div class="rrze-hub" style="display:flex;gap:16px;flex-wrap:wrap;margin-top:12px">';
        $items = [
            ['label'=>sprintf(__('All %s', 'rrze-answers'), $title), 'url'=>$all_url],
            ['label'=>sprintf(__('Add %s', 'rrze-answers'), $title), 'url'=>$add_url],
            ['label'=>__('Categories', 'rrze-answers'), 'url'=>$cat_url],
            ['label'=>__('Tags', 'rrze-answers'), 'url'=>$tag_url],
        ];
        foreach ($items as $i) {
            printf(
                '<a class="button button-secondary" style="min-width:180px;height:auto;padding:10px 16px;font-size:14px" href="%s">%s</a>',
                esc_url($i['url']),
                esc_html($i['label'])
            );
        }
        echo '</div></div>';
    }

    public function fixParentHighlight($parent_file) {
        $screen = get_current_screen();
        if (!$screen) return $parent_file;

        $targets = [$this->faq_pt, $this->glossary_pt, $this->placeholder_pt];
        if (in_array($screen->post_type, $targets, true) || in_array($screen->taxonomy ?? '', [$this->faq_cat, $this->faq_tag, $this->glossary_cat, $this->glossary_tag, $this->syn_group, $this->syn_tag], true)) {
            return $this->parent_slug;
        }
        return $parent_file;
    }

    public function fixSubmenuHighlight($submenu_file) {
        $screen = get_current_screen();
        if (!$screen) return $submenu_file;

        if ($screen->post_type === $this->faq_pt || in_array($screen->taxonomy ?? '', [$this->faq_cat, $this->faq_tag], true)) {
            return 'rrze-answers_faq';
        }
        if ($screen->post_type === $this->glossary_pt || in_array($screen->taxonomy ?? '', [$this->glossary_cat, $this->glossary_tag], true)) {
            return 'rrze-answers_glossary';
        }
        if ($screen->post_type === $this->placeholder_pt || in_array($screen->taxonomy ?? '', [$this->syn_group, $this->syn_tag], true)) {
            return 'rrze-answers_placeholder';
        }
        return $submenu_file;
    }
}

