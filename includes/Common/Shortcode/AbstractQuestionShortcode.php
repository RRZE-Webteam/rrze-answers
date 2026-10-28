<?php

namespace RRZE\Answers\Common\Shortcode;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\Content\QuestionListRenderer;
use RRZE\Answers\Common\Tools;

use function RRZE\Answers\plugin;

/**
 * WordPress shortcode and classic-editor integration for question lists.
 */
abstract class AbstractQuestionShortcode
{
    private array $settings;
    private string $type;

    protected function __construct(string $type, array $aliases = [])
    {
        $this->type = $type;
        $this->settings = $this->getShortcodeSettings();

        foreach (array_merge([$type], $aliases) as $shortcode) {
            add_shortcode($shortcode, [$this, 'shortcodeOutput']);
        }
        add_action('admin_head', [$this, 'setMCEConfig']);
        add_filter('mce_external_plugins', [$this, 'addMCEButtons']);
    }

    abstract public function getShortcodeSettings(): array;

    public function shortcodeOutput($atts, $content = null, $shortcode_tag = ''): string
    {
        // Preserve the workaround for escaped shortcodes in the block editor.
        if (($skip = Tools::preventGutenbergDoubleBracketBug($shortcode_tag)) !== false) {
            return $skip;
        }

        return QuestionListRenderer::render(
            $this->type,
            QuestionListAttributes::normalize($atts, $this->settings, $this->type)
        );
    }

    public function setMCEConfig(): void
    {
        $shortcode = '';
        foreach ($this->settings as $attribute => $details) {
            if ($attribute !== 'block') {
                $shortcode .= ' ' . $attribute . '=""';
            }
        }
        $pluginName = $this->settings['block']['blockname'];
        $shortcode = '[' . $pluginName . ' ' . $shortcode . ']';
        ?>
        <script type='text/javascript'>
            tmp = [{
                'name': <?php echo wp_json_encode($pluginName); ?>,
                'title': <?php echo wp_json_encode($this->settings['block']['title']); ?>,
                'icon': <?php echo wp_json_encode($this->settings['block']['tinymce_icon']); ?>,
                'shortcode': <?php echo wp_json_encode($shortcode); ?>,
            }];
            phpvar = (typeof phpvar === 'undefined' ? tmp : phpvar.concat(tmp));
        </script>
        <?php
    }

    public function addMCEButtons($pluginArray)
    {
        if (current_user_can('edit_posts') && current_user_can('edit_pages')) {
            $pluginArray['rrze_shortcode'] = plugin()->getUrl() . 'assets/js/tinymce-shortcodes.js';
        }
        return $pluginArray;
    }
}
