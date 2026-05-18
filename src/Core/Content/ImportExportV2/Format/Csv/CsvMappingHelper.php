<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * Small helper for the CSV format layer.
 *
 * CSV columns are flat, but record payload arrays are nested. This helper
 * translates between those two shapes so the CSV reader and writer do not each
 * have to reimplement dotted-path payload access.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CsvMappingHelper
{
    /**
     * Writes one value from a flat CSV column into a nested payload path.
     *
     * Example:
     * ```php
     * $payload = [];
     *
     * self::writeValueToRecordPath($payload, 'productNumber', 'SW10001');
     * ```
     *
     * Result:
     * ```php
     * [
     *     'productNumber' => 'SW10001',
     * ]
     * ```
     *
     * Another example:
     * ```php
     * self::writeValueToRecordPath($payload, 'tax.id', 'tax-123');
     * ```
     *
     * Result:
     * ```php
     * [
     *     'productNumber' => 'SW10001',
     *     'tax' => ['id' => 'tax-123'],
     * ]
     * ```
     *
     * We need this during CSV import because mappings like `product_number` ->
     * `productNumber` and `tax_id` -> `tax.id` must rebuild the nested payload
     * shape from flat columns.
     *
     * @param array<string, mixed> $payload
     */
    public static function writeValueToRecordPath(array &$payload, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = &$payload;

        foreach ($segments as $index => $segment) {
            if ($segment === '*') {
                throw ImportExportV2Exception::invalidPath($path);
            }

            $segment = ctype_digit($segment) ? (int) $segment : $segment;
            $isLast = $index === \count($segments) - 1;

            if ($isLast) {
                $current[$segment] = $value;

                return;
            }

            if (!isset($current[$segment]) || !\is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }
    }

    /**
     * Writes a flat CSV list column into a nested `*` payload path.
     *
     * Example:
     * ```php
     * $values = ['cat-1', 'cat-2'];
     *
     * self::writeListValuesToRecordListPath($payload, 'categories.*.id', $values);
     * ```
     *
     * Result:
     * ```php
     * [
     *     'categories' => [
     *         ['id' => 'cat-1'],
     *         ['id' => 'cat-2'],
     *     ],
     * ]
     * ```
     *
     * Another example:
     * ```php
     * self::writeListValuesToRecordListPath($payload, 'categoryTree.*', ['cat-1', 'cat-2']);
     * ```
     *
     * Result:
     * ```php
     * [
     *     'categoryTree' => ['cat-1', 'cat-2'],
     * ]
     * ```
     *
     * We need this during CSV import for columns like `category_ids`, where a
     * flat string such as `cat-1|cat-2` is split first and then rebuilt into
     * the nested payload list structure.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $values
     */
    public static function writeListValuesToRecordListPath(array &$payload, string $path, array $values): void
    {
        $listPath = self::parseListPath($path);
        if ($listPath === null) {
            throw ImportExportV2Exception::invalidPath($path);
        }

        $prefix = $listPath['prefix'];
        $suffix = $listPath['suffix'];
        $existingItems = self::readValueFromRecordPath($payload, $prefix);
        $items = \is_array($existingItems) ? $existingItems : [];

        foreach ($values as $index => $value) {
            if ($suffix === '') {
                $items[$index] = $value;

                continue;
            }

            $item = isset($items[$index]) && \is_array($items[$index]) ? $items[$index] : [];
            self::writeValueToRecordPath($item, $suffix, $value);
            $items[$index] = $item;
        }

        self::writeValueToRecordPath($payload, $prefix, array_values($items));
    }

    /**
     * Reads one nested value from a payload path so it can be written back into a
     * flat CSV column.
     *
     * Example:
     * ```php
     * $payload = [
     *     'productNumber' => 'SW10001',
     *     'tax' => ['id' => 'tax-123'],
     * ];
     *
     * self::readValueFromRecordPath($payload, 'productNumber'); // 'SW10001'
     * self::readValueFromRecordPath($payload, 'tax.id'); // 'tax-123'
     * self::readValueFromRecordPath($payload, 'manufacturer.id'); // null
     * ```
     *
     * We need this during CSV export for simple one-column mappings such as
     * `productNumber` or `tax.id`.
     *
     * @param array<string, mixed> $payload
     */
    public static function readValueFromRecordPath(array $payload, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $payload;

        foreach ($segments as $segment) {
            $segment = ctype_digit($segment) ? (int) $segment : $segment;

            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Reads a nested `*` list path from a payload and flattens it into strings
     * that can be joined into one CSV column.
     *
     * Example:
     * ```php
     * $payload = [
     *     'categories' => [
     *         ['id' => 'cat-1'],
     *         ['id' => 'cat-2'],
     *     ],
     * ];
     *
     * self::readListValuesFromRecordListPath($payload, 'categories.*.id');
     * // ['cat-1', 'cat-2']
     * ```
     *
     * Another example:
     * ```php
     * $payload = [
     *     'categoryTree' => ['cat-1', 'cat-2'],
     * ];
     *
     * self::readListValuesFromRecordListPath($payload, 'categoryTree.*');
     * // ['cat-1', 'cat-2']
     * ```
     *
     * We need this during CSV export for list columns such as `category_ids`.
     * The writer later joins the returned values with the configured separator,
     * for example `cat-1|cat-2`.
     *
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    public static function readListValuesFromRecordListPath(array $payload, string $path): array
    {
        $listPath = self::parseListPath($path);
        if ($listPath === null) {
            return [];
        }

        $prefix = $listPath['prefix'];
        $suffix = $listPath['suffix'];
        $list = self::readValueFromRecordPath($payload, $prefix);
        if (!\is_array($list)) {
            return [];
        }

        $values = [];
        foreach ($list as $item) {
            if ($suffix === '') {
                $value = $item;
            } elseif (\is_array($item)) {
                $value = self::readValueFromRecordPath($item, $suffix);
            } else {
                $value = null;
            }

            if ($value === null) {
                continue;
            }

            $values[] = (string) $value;
        }

        return $values;
    }

    /**
     * Supports both:
     *
     * - `association.*.field`
     * - `plainListField.*`
     *
     * @return array{prefix: string, suffix: string}|null
     */
    private static function parseListPath(string $path): ?array
    {
        if (str_contains($path, '.*.')) {
            [$prefix, $suffix] = explode('.*.', $path, 2);

            return $prefix !== '' ? ['prefix' => $prefix, 'suffix' => $suffix] : null;
        }

        if (str_ends_with($path, '.*')) {
            $prefix = substr($path, 0, -2);

            return $prefix !== '' ? ['prefix' => $prefix, 'suffix' => ''] : null;
        }

        return null;
    }
}
