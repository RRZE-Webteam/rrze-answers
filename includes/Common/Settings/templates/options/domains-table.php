<?php

namespace RRZE\Answers\Common\Settings;

defined('ABSPATH') || exit;

use RRZE\Answers\Common\API\SyncAPI;

?>
<tr>
    <td colspan="2">
        <?php

        $api = new SyncAPI();
        $aDomains = $api->getDomains();

        if (count($aDomains) > 0) {
            $i = 1;
            echo '<style> .settings_page_rrze-faq #log .form-table th {width:0;}</style>';
            echo '<table class="wp-list-table widefat striped"><tbody>';
            foreach ($aDomains as $name => $url) {
                echo '<tr><td><input type="checkbox" name="del_domain_' . esc_attr($i) . '" value="' . esc_url($url) . '"></td><td>' . esc_html($name) . '</td><td>' . esc_url($url) . '</td></tr>';
                $i++;
            }
            echo '</tbody></table>';
            echo '<p>' . esc_html__('Please note: "Delete selected domains" will DELETE every FAQ on this website that has been fetched from the selected domains.', 'rrze-faq') . '</p>';
            submit_button(esc_html__('Delete selected domains', 'rrze-faq'));
        }
        ?>
    </td>
</tr>