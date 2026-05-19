<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Format\Csv;

use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Record\RecordPathWalker;
use Shopware\Core\Framework\Log\Package;

/**
 * CSV-specific mapping helper between flat columns and nested record payloads.
 *
 * JSON can carry the record tree directly. CSV cannot, so the reader and
 * writer need a small set of conventions for:
 * - scalar dotted paths like `tax.id`
 * - one-level list paths like `tags.*.name`
 * - flat list cells like `tag-1|tag-2`
 *
 * Important limitation: CSV currently supports only one wildcard list level.
 * Paths like `tags.*.name` and `categoryTree.*` work, but nested wildcard
 * paths such as `lineItems.*.tags.*.name` are intentionally not supported.
 * JSON can represent that structure directly; CSV would require more
 * complex conventions and parsing logic that we want to avoid for now.
 *
 * Scalar dotted-path access is delegated to the shared `RecordPathWalker`.
 * This helper only keeps the CSV-specific list parsing and validation rules.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CsvMappingHelper
{
    /**
     * Writes one scalar CSV column value into a nested record payload path.
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
     * Used during CSV import for simple mappings like:
     * - `product_number` -> `productNumber`
     * - `tax_id` -> `tax.id`
     *
     * @param array<string, mixed> $payload
     */
    public static function writeValueToRecordPath(array &$payload, string $path, mixed $value): void
    {
        foreach (explode('.', $path) as $segment) {
            if ($segment === '*') {
                throw ImportExportV2Exception::invalidPath($path);
            }
        }

        RecordPathWalker::writeValue($payload, $path, $value);
    }

    /**
     * Writes one split CSV list column into a one-level `*` record path.
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
     * Used during CSV import after a cell like `cat-1|cat-2` has already been
     * split into `['cat-1', 'cat-2']`.
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
     * Reads one scalar value from a nested record payload for CSV export.
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
     * Used during CSV export for simple one-column mappings such as
     * `productNumber` or `tax.id`.
     *
     * @param array<string, mixed> $payload
     */
    public static function readValueFromRecordPath(array $payload, string $path): mixed
    {
        if (in_array('*', explode('.', $path), true)) {
            return null;
        }

        return RecordPathWalker::readValue($payload, $path);
    }

    /**
     * Reads one one-level `*` list path and flattens it into CSV cell values.
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
     * Used during CSV export for list columns such as `category_ids`. The
     * caller later joins the returned values with the configured separator,
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
     * Parses the two CSV list path shapes we currently support:
     * - `association.*.field`
     * - `plainListField.*`
     *
     * Nested wildcard paths are rejected here on purpose.
     *
     * @return array{prefix: string, suffix: string}|null
     */
    private static function parseListPath(string $path): ?array
    {
        if (str_contains($path, '.*.')) {
            [$prefix, $suffix] = explode('.*.', $path, 2);

            if ($prefix === '' || str_contains($suffix, '.*')) {
                return null;
            }

            return ['prefix' => $prefix, 'suffix' => $suffix];
        }

        if (str_ends_with($path, '.*')) {
            $prefix = substr($path, 0, -2);

            return $prefix !== '' ? ['prefix' => $prefix, 'suffix' => ''] : null;
        }

        return null;
    }
}
