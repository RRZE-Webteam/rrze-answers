<?php

namespace RRZE\FAQ;

defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-includes/class-wp-widget.php';

use RRZE\Answers\Config;


// Creating the widget
class FAQWidget extends \WP_Widget
{
    private $cpt = [];

    public function __construct()
    {
        $this->cpt = Config::getConstants('cpt');

        parent::__construct(
            'faq_widget',
            __('FAQ Widget', 'rrze-faq'),
            array('description' => __('Displays a FAQ', 'rrze-faq'))
        );
    }

    public function getRandomFAQID($catID)
    {
        $aFaqIDs = get_posts([ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'posts_per_page' => -1,
            'post_type' => $this->cpt['faq'],
            'fields' => 'ids',
            'tax_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                [
                    'taxonomy' => $this->cpt['category'],
                    'field' => 'term_id',
                    'terms' => $catID,
                ]
            ],
        ]);
        if (!empty($aFaqIDs)) {
            return $aFaqIDs[array_rand($aFaqIDs, 1)];
        } else {
            return 0;
        }
    }

    // Creating widget front-end
    public function widget($args, $instance)
    {
        $start = ($instance['start'] ? wp_date('Y-m-d', strtotime($instance['start'])) : '');
        $end = ($instance['end'] ? wp_date('Y-m-d', strtotime($instance['end'])) : '');

        if ($start || $end) {
            $today = wp_date('Y-m-d');
            if (($start && $today < $start) || ($end && $today > $end)) {
                return;
            }
        }

        $id = (isset($instance['id']) ? intval($instance['id']) : 0);
        $catID = (isset($instance['catID']) ? intval($instance['catID']) : 0);

        $id = ($id ? $id : ($catID ? $this->getRandomFAQID($catID) : 0));

        if ($id) {
            $attributes = (isset($instance['display']) ? intval($instance['display']) : '');
            switch ($attributes) {
                case 1:
                    $attributes = '';
                    break;
                case 2:
                    $attributes = "show='load-open'";
                    break;
                case 3:
                    $attributes = "hide='title'";
                    break;
            }

            // Escape before output
            echo wp_kses_post($args['before_widget']); // Escaping HTML
            echo do_shortcode('[faq id="' . esc_attr($id) . '" ' . esc_attr($attributes) . ']'); // Escape the shortcode attributes
            echo wp_kses_post($args['after_widget']); // Escaping HTML
        }
    }

    public function dropdownFAQs($selectedID = 0)
    {
        $args = [
            'post_type' => $this->cpt['faq'],
            'pagination' => false,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'cache_results' => true,
            'cache_post_meta_cache' => true,
            'order' => 'ASC',
            'orderby' => 'post_title',
        ];

        $posts = get_posts($args);
        $output = '';

        if (!empty($posts)) {
            $output = "<p><label for='" . esc_attr($this->get_field_id('id')) . "'>" . esc_html(__('Choose a FAQ', 'rrze-faq')) . ":</label> ";
            $output .= "<select id='" . esc_attr($this->get_field_id('id')) . "' name='" . esc_attr($this->get_field_name('id')) . "' class='widefat'>";
            $output .= "<option value='0'>---</option>";
            foreach ($posts as $post) {
                $sSelected = selected($selectedID, $post->ID, false);
                $output .= "<option value='" . esc_attr($post->ID) . "' $sSelected>" . esc_html($post->post_title) . "</option>";
            }
            $output .= "</select></p>";
        }

        $html = apply_filters('dropdownFAQs', $output, $args, $posts);
        echo wp_kses_post($html);
    }

    public function displaySelect($selectedID = 0)
    {
        $aOptions = [
            1 => __('show question and answer', 'rrze-faq'),
            2 => __('show question and answer opened', 'rrze-faq'),
            3 => __('hide question', 'rrze-faq'),
        ];
        $output = "<p><label for='" . esc_attr($this->get_field_id('display')) . "'>" . esc_html(__('Display options:', 'rrze-faq')) . ":</label>";
        $output .= "<select id='" . esc_attr($this->get_field_id('display')) . "' name='" . esc_attr($this->get_field_name('display')) . "' class='widefat'>";
        foreach ($aOptions as $ID => $txt) {
            $sSelected = selected($selectedID, $ID, false);
            $output .= "<option value='" . esc_attr($ID) . "' $sSelected>" . esc_html($txt) . "</option>";
        }
        $output .= "</select></p>";
        echo wp_kses_post($output);
    }


    public function dateFields($dates)
    {
        $aFields = [
            'start' => __('Start', 'rrze-faq'),
            'end' => __('End', 'rrze-faq'),
        ];
        $output = '';
        foreach ($aFields as $field => $label) {
            $val = isset($dates[$field]) ? esc_attr($dates[$field]) : ''; // Sanitize the value
            $output .= "<p><label for='" . esc_attr($field) . "'>" . esc_html($label) . ":</label><br>";
            $output .= "<input type='date' id='" . esc_attr($this->get_field_id($field)) . "' name='" . esc_attr($this->get_field_name($field)) . "' value='" . esc_attr($val) . "' class='widefat'></p>";
        }
        echo wp_kses_post($output);
    }

    // Widget Backend
    public function form($instance)
    {
        $id = (isset($instance['id']) ? intval($instance['id']) : 0);
        $catID = (isset($instance['catID']) ? intval($instance['catID']) : 0);
        $dates = [
            'start' => (isset($instance['start']) ? esc_attr($instance['start']) : ''),
            'end' => (isset($instance['end']) ? esc_attr($instance['end']) : ''),
        ];
        $display = (isset($instance['display']) ? intval($instance['display']) : 0);

        $this->dropdownFAQs($id);

        $args = [
            'show_option_none' => '---',
            'name' => esc_attr($this->get_field_name('catID')),
            'taxonomy' => $this->cpt['category'],
            'hide_empty' => 0,
            'orderby' => 'name',
            'selected' => $catID,
            'class' => 'widefat',
        ];

        echo "<p><label for='" . esc_attr($this->get_field_name('catID')) . "'>" . esc_html(__('or choose a Category to display a FAQ randomly', 'rrze-faq')) . ":</label>";
        wp_dropdown_categories($args);
        echo '</p>';

        $this->dateFields($dates);
        $this->displaySelect($display);
    }

    // Updating widget replacing old instances with new
    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['id'] = (isset($new_instance['id']) ? $new_instance['id'] : 0);
        $instance['catID'] = (isset($new_instance['catID']) ? $new_instance['catID'] : 0);
        $instance['start'] = (isset($new_instance['start']) ? $new_instance['start'] : '');
        $instance['end'] = (isset($new_instance['end']) ? $new_instance['end'] : '');
        $instance['display'] = (isset($new_instance['display']) ? $new_instance['display'] : 0);
        return $instance;
    }
}
