<?php
use RRZE\Answers\Common\Blocks\BlockAttributes;

$attributes = BlockAttributes::normalize(
    (array) ($attributes ?? []),
    $block ?? null,
    [],
    ['id']
);

$atts = [];
foreach ($attributes as $key => $value) {
    if ($value === '' || $value === null || $value === []) {
        continue;
    }
    if (is_array($value)) {
        $value = implode(',', $value);
    }
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }
    $atts[] = sprintf('%s="%s"', $key, esc_attr((string) $value));
}

echo do_shortcode('[placeholder ' . implode(' ', $atts) . ']');
