<?php

namespace RRZE\Answers\Common\Settings;

defined('ABSPATH') || exit;

$tour_attr = $option->getName() === 'api_active_rrze_faq' ? ' data-rrze-tour="permissions-import"' : '';
?>
<tr valign="top"<?php echo $tour_attr; ?>>
    <th scope="row" class="rrze-wp-form-label">
        <label for="<?php echo $option->getIdAttribute(); ?>" <?php echo $option->getLabelClassAttribute(); ?>><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="rrze-wp-form rrze-wp-form-input">
        <label>
            <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="checkbox" value="1" <?php checked($option->isChecked()); ?> <?php echo $option->getInputClassAttribute(); ?>>
            <?php echo $option->getArg('description'); ?>
        </label>

        <input type="hidden" name="wp_settings_submitted[]" value="<?php echo esc_attr($option->getName()); ?>">

        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-answers-settings-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>