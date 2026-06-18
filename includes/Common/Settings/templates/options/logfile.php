<?php

namespace RRZE\Answers\Common\Settings;

use RRZE\Answers\Common\Tools;

defined('ABSPATH') || exit;

?>
<tr>
    <td colspan="2" data-rrze-tour="logfile-content">
        <?php
        $lines = Tools::readLogfileLines();

        if ($lines !== false && $lines !== []) {
            echo '<style> .settings_page_rrze-answers #faqlog .form-table th {width:0;}</style><table class="wp-list-table widefat striped"><tbody>';
            foreach ($lines as $line) {
                echo wp_kses_post('<tr><td>' . esc_html($line) . '</td></tr>');
            }
            echo '</tbody></table>';
        } else {
            echo esc_html(__('Logfile is empty.', 'rrze-answers'));
        }
        ?>
    </td>
</tr>