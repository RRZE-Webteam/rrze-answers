<?php

namespace RRZE\Answers\Common\Settings;

defined('ABSPATH') || exit;
?>
<?php if ($linkedSections = $settings->getActiveTab()->getSectionLinks()) { ?>
    <ul class="subsubsub rrze-answers-section-menu">
        <?php foreach ($linkedSections as $section) { ?>
            <li><a href="<?php echo $settings->getUrl(); ?>&tab=<?php echo $section->tab->slug; ?>&section=<?php echo $section->slug; ?>" class="<?php echo $section->slug == $settings->getActiveTab()->getActiveSection()->slug ? 'current' : null; ?>"><?php echo $section->title; ?></a> | </li>
        <?php } ?>
    </ul>
<?php } ?>