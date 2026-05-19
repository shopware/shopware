<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Record;

use Shopware\Core\Framework\Log\Package;

/**
 * Shared dotted-path helper for the in-memory record/payload builders.
 *
 * This is the generic tree traversal used by the JSON-side builders. Unlike
 * CSV, it can preserve nested list structures directly, so it supports
 * multiple wildcard levels such as `lineItems.*.tags.*.name`.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class RecordPathWalker
{
    public static function readValue(array $source, string $path): mixed
    {
        return self::readSegments($source, explode('.', $path));
    }

    /**
     * @param array<string, mixed> $target
     */
    public static function writeValue(array &$target, string $path, mixed $value): void
    {
        self::writeSegments($target, explode('.', $path), $value);
    }

    /**
     * Recursively reads one dotted path from an array tree.
     *
     * Normal segments descend into nested objects. `*` segments descend into
     * every list item and collect their results back into an array.
     *
     * Example source:
     * ```php
     * [
     *     'tax' => ['id' => 'tax-123'],
     *     'tags' => [
     *         ['name' => 'Featured'],
     *         ['name' => 'Sale'],
     *     ],
     *     'lineItems' => [
     *         [
     *             'tags' => [
     *                 ['name' => 'Featured'],
     *                 ['name' => 'Sale'],
     *             ],
     *         ],
     *         [
     *             'tags' => [
     *                 ['name' => 'New'],
     *             ],
     *         ],
     *     ],
     * ]
     * ```
     *
     * Reads:
     * - `tax.id` -> `tax-123`
     * - `tags.*.name` -> `['Featured', 'Sale']`
     * - `lineItems.*.tags.*.name` -> `[['Featured', 'Sale'], ['New']]`
     *
     * @param array<int, string> $segments
     */
    private static function readSegments(mixed $source, array $segments): mixed
    {
        if ($segments === []) {
            return $source;
        }

        $segment = array_shift($segments);
        \assert(\is_string($segment));

        if ($segment === '*') {
            if (!\is_array($source)) {
                return null;
            }

            $values = [];
            foreach ($source as $item) {
                $value = self::readSegments($item, $segments);
                if ($value === null) {
                    continue;
                }

                $values[] = $value;
            }

            return $values;
        }

        $segment = ctype_digit($segment) ? (int) $segment : $segment;
        if (!\is_array($source) || !\array_key_exists($segment, $source)) {
            return null;
        }

        return self::readSegments($source[$segment], $segments);
    }

    /**
     * Recursively writes one dotted path back into an array tree.
     *
     * Normal segments create nested arrays as needed. `*` segments merge list
     * items by index so sibling paths like `tags.*.id` and `tags.*.name` end
     * up in the same item.
     *
     * Examples:
     * - writing `tax.id` with `'tax-123'` produces `['tax' => ['id' => 'tax-123']]`
     * - writing `tags.*.name` with `['Featured', 'Sale']` produces
     *   `['tags' => [['name' => 'Featured'], ['name' => 'Sale']]]`
     * - writing `tags.*.id` after that merges by index into
     *   `['tags' => [['name' => 'Featured', 'id' => 'tag-1'], ['name' => 'Sale', 'id' => 'tag-2']]]`
     *
     * @param array<string, mixed>|list<mixed> $target
     * @param array<int, string> $segments
     */
    private static function writeSegments(array &$target, array $segments, mixed $value): void
    {
        $segment = array_shift($segments);
        \assert(\is_string($segment));

        if ($segment === '*') {
            if (!\is_array($value)) {
                return;
            }

            $items = $target;
            foreach ($value as $index => $itemValue) {
                if ($segments === []) {
                    $items[$index] = $itemValue;

                    continue;
                }

                $item = isset($items[$index]) && \is_array($items[$index]) ? $items[$index] : [];
                self::writeSegments($item, $segments, $itemValue);
                $items[$index] = $item;
            }

            $target = array_values($items);

            return;
        }

        $segment = ctype_digit($segment) ? (int) $segment : $segment;
        if ($segments === []) {
            $target[$segment] = $value;

            return;
        }

        $child = isset($target[$segment]) && \is_array($target[$segment]) ? $target[$segment] : [];
        self::writeSegments($child, $segments, $value);
        $target[$segment] = $child;
    }
}
