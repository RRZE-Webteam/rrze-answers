<?php

declare(strict_types=1);

namespace RRZE\Answers\Common\Blocks;

/**
 * Normalize typed block attributes while retaining legacy delimiter values.
 */
final class BlockAttributes
{
    /**
     * @param array<string, mixed> $attributes Prepared block attributes.
     * @param mixed $block Current WP_Block instance, when available.
     * @param string[] $stringLists Array attributes containing strings.
     * @param string[] $integerLists Array attributes containing integers.
     * @param string[] $booleans Boolean attributes.
     * @param string[] $integers Scalar integer attributes.
     * @return array<string, mixed>
     */
    public static function normalize(
        array $attributes,
        $block = null,
        array $stringLists = [],
        array $integerLists = [],
        array $booleans = [],
        array $integers = []
    ): array {
        $rawAttributes = self::getRawAttributes($block);

        foreach ($stringLists as $attributeName) {
            $value = self::attributeValue($attributeName, $attributes, $rawAttributes, []);
            $attributes[$attributeName] = self::normalizeStringList($value);
        }

        foreach ($integerLists as $attributeName) {
            $value = self::attributeValue($attributeName, $attributes, $rawAttributes, []);
            $attributes[$attributeName] = self::normalizeIntegerList($value);
        }

        foreach ($booleans as $attributeName) {
            $value = self::attributeValue($attributeName, $attributes, $rawAttributes, false);
            $attributes[$attributeName] = self::normalizeBoolean($value);
        }

        foreach ($integers as $attributeName) {
            $value = self::attributeValue($attributeName, $attributes, $rawAttributes, 0);
            $attributes[$attributeName] = (int) $value;
        }

        return $attributes;
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    public static function normalizeStringList($value): array
    {
        $values = self::listValues($value);
        $values = array_map(
            static fn ($item): string => trim((string) $item),
            $values
        );

        return array_values(array_unique(array_filter(
            $values,
            static fn (string $item): bool => $item !== ''
        )));
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    public static function normalizeIntegerList($value): array
    {
        $values = array_map('intval', self::listValues($value));

        return array_values(array_unique(array_filter(
            $values,
            static fn (int $item): bool => $item > 0
        )));
    }

    /**
     * @param mixed $value
     */
    public static function normalizeBoolean($value): bool
    {
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $value === true || $value === 1;
    }

    /**
     * @param mixed $block
     * @return array<string, mixed>
     */
    private static function getRawAttributes($block): array
    {
        if (!($block instanceof \WP_Block)) {
            return [];
        }

        $rawAttributes = $block->parsed_block['attrs'] ?? [];

        return is_array($rawAttributes) ? $rawAttributes : [];
    }

    /**
     * Prefer the raw delimiter value when present. WordPress replaces values
     * that do not match the current schema with the current default before it
     * invokes a dynamic block's render callback.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $rawAttributes
     * @param mixed $default
     * @return mixed
     */
    private static function attributeValue(
        string $attributeName,
        array $attributes,
        array $rawAttributes,
        $default
    ) {
        if (array_key_exists($attributeName, $rawAttributes)) {
            return $rawAttributes[$attributeName];
        }

        return $attributes[$attributeName] ?? $default;
    }

    /**
     * @param mixed $value
     * @return mixed[]
     */
    private static function listValues($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        return explode(',', (string) $value);
    }
}
