<?php

namespace RRZE\Answers\Common\Settings;

defined('ABSPATH') || exit;

$tour_attr = '';
if ($option->getName() === 'new_url') {
    $tour_attr = ' data-rrze-tour="new-domain"';
} elseif ($option->getName() === 'custom_faq_slug') {
    $tour_attr = ' data-rrze-tour="permalink-settings"';
}
?>
<tr valign="top">
    <th scope="row" class="rrze-wp-form-label">
        <label for="<?php echo $option->getIdAttribute(); ?>" <?php echo $option->getLabelClassAttribute(); ?>><?php echo $option->getLabel(); ?></label>
    </th>
    <td class="rrze-wp-form rrze-wp-form-input"<?php echo $tour_attr; ?>>
        <input name="<?php echo esc_attr($option->getNameAttribute()); ?>" id="<?php echo $option->getIdAttribute(); ?>" type="text" value="<?php echo $option->getValueAttribute(); ?>" synonym="<?php echo $option->getsynonymAttribute() ?: ''; ?>" <?php echo $option->getInputClassAttribute(); ?>>
        <?php if ($description = $option->getArg('description')) { ?>
            <p class="description"><?php echo $description; ?></p>
        <?php } ?>
        <?php if ($error = $option->hasError()) { ?>
            <div class="rrze-answers-settings-error"><?php echo $error; ?></div>
        <?php } ?>
    </td>
</tr>