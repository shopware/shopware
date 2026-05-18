<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Record;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * Note: This can feel a bit complex, so should have thorough tests, hopefully once it works, it should not change much anymore.
 *       Also it was mostly AI generated, so maybe a simpler solution is also possible.
 *
 * Projects one DAL entity into one shared export record.
 *
 * The profile declares a list of record paths, and this builder copies exactly those values out of the loaded
 * DAL entity into one `payload` array.
 *
 * Example profile record paths:
 *
 * - `productNumber`
 * - `active`
 * - `tax.id`
 * - `translations.DEFAULT.name`
 * - `tags.*.id`
 * - `tags.*.name`
 * - `categoryTree.*`
 *
 * Example DAL entity values after loading and serializing:
 *
 * - `productNumber = SW10001`
 * - `active = true`
 * - `taxId = tax-123`
 * - `translated.name = Demo product`
 * - `tags = [['id' => 'tag-1', 'name' => 'Featured'], ['id' => 'tag-2', 'name' => 'Sale']]`
 * - `categoryTree = ['cat-1', 'cat-2']`
 * - `customFields.exportedPrice = CalculatedPrice(...)`
 *
 * Example export record produced by this builder:
 *
 * ```php
 * new ImportExportRecord(
 *     'product',
 *     [
 *         'productNumber' => 'SW10001',
 *         'active' => true,
 *         'tax' => ['id' => 'tax-123'],
 *         'translations' => [
 *             'DEFAULT' => ['name' => 'Demo product'], // TODO: maybe DEFAULT should be replaced by the actual language code or id?
 *         ],
 *         'tags' => [
 *             ['id' => 'tag-1', 'name' => 'Featured'],
 *             ['id' => 'tag-2', 'name' => 'Sale'],
 *         ],
 *         'categoryTree' => [
 *             'cat-1',
 *             'cat-2',
 *         ],
 *         'customFields' => [
 *             'exportedPrice' => [
 *                 'unitPrice' => 10.0,
 *             ],
 *         ],
 *     ]
 * );
 * ```
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportRecordBuilder
{
    public function __construct(private readonly DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
    }

    /**
     * Builds one export record for one loaded root entity.
     *
     * This is the main entrypoint used by the export processor:
     *
     * 1. load the root DAL definition for the profile entity
     * 2. serialize the DAL entity into a plain nested array
     * 3. copy each configured recordPath of the profile into the export payload
     * 4. wrap the final payload in an `ImportExportRecord`
     *
     * Example:
     *
     * If the profile exports:
     *
     * - `productNumber`
     * - `tax.id`
     * - `tags.*.id`
     * - `tags.*.name`
     * - `categoryTree.*`
     * - `customFields.exportedPrice.unitPrice`
     *
     * then this method produces:
     *
     * ```php
     * new ImportExportRecord('product', [
     *     'productNumber' => 'SW10001',
     *     'tax' => ['id' => 'tax-123'],
     *     'tags' => [
     *         ['id' => 'tag-1', 'name' => 'Featured'],
     *         ['id' => 'tag-2', 'name' => 'Sale'],
     *     ],
     *     'categoryTree' => [
     *         'cat-1',
     *         'cat-2',
     *     ],
     *     'customFields' => [
     *         'exportedPrice' => [
     *             'unitPrice' => 10.0,
     *         ],
     *     ],
     * ]);
     * ```
     */
    public function build(Entity $entity, ImportExportV2ProfileEntity $profile): ImportExportRecord
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($profile->getEntity());
        $serialized = $this->normalizeSerializedValue($entity->jsonSerialize());
        \assert(\is_array($serialized));

        $payload = [];
        foreach ($profile->getRecordPaths() as $path) {
            $this->writePayloadValue($payload, $serialized, $definition, $path);
        }

        return new ImportExportRecord($profile->getEntity(), $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $serialized
     */
    private function writePayloadValue(array &$payload, array $serialized, EntityDefinition $definition, string $path): void
    {
        // List paths such as `tags.*.name` or `categoryTree.*` need different
        // handling from
        // simple scalar paths. We first flatten the list of values out of the
        // serialized entity and then rebuild the nested export shape.
        //
        // Example:
        //
        // path: `tags.*.name`
        // source: `['tags' => [['id' => 'tag-1', 'name' => 'Featured'], ['id' => 'tag-2', 'name' => 'Sale']]]`
        // result written into payload:
        //
        // ```php
        // [
        //     'tags' => [
        //         ['name' => 'Featured'],
        //         ['name' => 'Sale'],
        //     ],
        // ]
        // ```
        //
        // Another example for a plain scalar id list:
        //
        // path: `categoryTree.*`
        // source: `['categoryTree' => ['cat-1', 'cat-2']]`
        // result written into payload:
        //
        // ```php
        // [
        //     'categoryTree' => ['cat-1', 'cat-2'],
        // ]
        // ```
        if ($this->parseListPath($path) !== null) {
            $values = $this->readListValues($serialized, $path);
            if ($values !== []) {
                $this->writeListValues($payload, $path, $values);
            }

            return;
        }

        $value = $this->readScalarValue($serialized, $definition, $path);
        if ($value === null) {
            // Missing values are simply skipped. Export profiles describe what
            // should be included when present, not a strict "all fields must
            // exist" contract.
            return;
        }

        $this->writeValue($payload, $path, $value);
    }

    /**
     * @param array<string, mixed> $serialized
     */
    private function readScalarValue(array $serialized, EntityDefinition $definition, string $path): mixed
    {
        // Translation paths are expressed in a profile-friendly way such as
        // `translations.DEFAULT.name`, but Shopware serializes translated data
        // under the separate `translated` key.
        //
        // Example:
        //
        // requested path: `translations.DEFAULT.name`
        // serialized entity: `['translated' => ['name' => 'Demo product']]`
        // exported value: `'Demo product'`
        //
        // We intentionally do not interpret the language segment here. For the
        // current export flow, `DEFAULT` means "put the currently resolved
        // translated value back into the record path expected by the profile".
        if (str_starts_with($path, 'translations.')) {
            $segments = explode('.', $path);
            $fieldName = $segments[2] ?? null;

            // Shopware serializes translated values separately; export folds
            // them back into the record path expected by the profile.
            return \is_string($fieldName) ? ($serialized['translated'][$fieldName] ?? $serialized[$fieldName] ?? null) : null;
        }

        $segments = explode('.', $path);
        $topLevel = $segments[0] ?? '';

        $field = $definition->getField($topLevel);
        if ($field instanceof ManyToOneAssociationField && \count($segments) === 2 && $segments[1] === 'id') {
            $fkField = $definition->getFields()->getByStorageName($field->getStorageName());

            if ($fkField instanceof FkField) {
                // Prefer the stored foreign key for nested many-to-one ids,
                // because the association itself may be partially loaded.
                //
                // Example:
                //
                // requested path: `tax.id`
                // serialized entity may contain:
                // - `taxId = tax-123`
                // - `tax = [...]` or even no fully materialized `tax`
                //
                // In that case the foreign key is the most reliable source for
                // the exported `tax.id` value.
                return $serialized[$fkField->getPropertyName()] ?? $this->readValue($serialized, $path);
            }
        }

        // Simple examples handled by the generic path reader:
        //
        // - `productNumber` -> `'SW10001'`
        // - `active` -> `true`
        // - `manufacturer.media.id` -> nested lookup in the serialized entity
        return $this->readValue($serialized, $path);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeValue(array &$payload, string $path, mixed $value): void
    {
        // Writes one scalar value into the nested export payload structure.
        //
        // Example:
        //
        // path: `tax.id`
        // value: `tax-123`
        //
        // result:
        //
        // ```php
        // [
        //     'tax' => [
        //         'id' => 'tax-123',
        //     ],
        // ]
        // ```
        //
        // Another example:
        //
        // path: `translations.DEFAULT.name`
        // value: `Demo product`
        //
        // result:
        //
        // ```php
        // [
        //     'translations' => [
        //         'DEFAULT' => [
        //             'name' => 'Demo product',
        //         ],
        //     ],
        // ]
        // ```
        $segments = explode('.', $path);
        $current = &$payload;

        foreach ($segments as $index => $segment) {
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
     * @param array<string, mixed> $payload
     * @param list<string> $values
     */
    private function writeListValues(array &$payload, string $path, array $values): void
    {
        // Rebuilds a list path back into the record payload structure.
        //
        // The current export flow supports both:
        //
        // - `association.*.field`
        // - `plainListField.*`
        //
        // Multiple list paths with the same prefix merge into the same list
        // items by index.
        //
        // Example:
        //
        // 1. `tags.*.id` with `['tag-1', 'tag-2']`
        // 2. `tags.*.name` with `['Featured', 'Sale']`
        //
        // final result:
        //
        // ```php
        // [
        //     'tags' => [
        //         ['id' => 'tag-1', 'name' => 'Featured'],
        //         ['id' => 'tag-2', 'name' => 'Sale'],
        //     ],
        // ]
        // ```
        //
        // Example for a scalar list:
        //
        // path: `categoryTree.*`
        // values: `['cat-1', 'cat-2']`
        //
        // result:
        //
        // ```php
        // [
        //     'categoryTree' => ['cat-1', 'cat-2'],
        // ]
        // ```
        ['prefix' => $prefix, 'suffix' => $suffix] = $this->parseListPath($path) ?? ['prefix' => '', 'suffix' => ''];

        $existingItems = $this->readValue($payload, $prefix);
        $items = \is_array($existingItems) ? $existingItems : [];

        foreach ($values as $index => $value) {
            if ($suffix === '') {
                $items[$index] = $value;

                continue;
            }

            $item = isset($items[$index]) && \is_array($items[$index]) ? $items[$index] : [];
            $this->writeValue($item, $suffix, $value);
            $items[$index] = $item;
        }

        $this->writeValue($payload, $prefix, array_values($items));
    }

    /**
     * @param array<string, mixed> $source
     */
    private function readValue(array $source, string $path): mixed
    {
        // Reads one value from the serialized entity using a dotted path.
        //
        // Example:
        //
        // source:
        // ```php
        // [
        //     'productNumber' => 'SW10001',
        //     'manufacturer' => [
        //         'media' => [
        //             'id' => 'media-123',
        //         ],
        //     ],
        // ]
        // ```
        //
        // reads:
        // - `productNumber` -> `SW10001`
        // - `manufacturer.media.id` -> `media-123`
        //
        // Missing segments return `null`, which lets the export flow simply
        // skip unavailable values.
        $segments = explode('.', $path);
        $current = $source;

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
     * @param array<string, mixed> $source
     *
     * @return list<string>
     */
    private function readListValues(array $source, string $path): array
    {
        // Reads a list path from the serialized entity and flattens it into a
        // simple list of string values.
        //
        // Example:
        //
        // path: `tags.*.name`
        // source:
        //
        // ```php
        // [
        //     'tags' => [
        //         ['id' => 'tag-1', 'name' => 'Featured'],
        //         ['id' => 'tag-2', 'name' => 'Sale'],
        //     ],
        // ]
        // ```
        //
        // result:
        //
        // ```php
        // ['Featured', 'Sale']
        // ```
        //
        // Example for a scalar id list:
        //
        // path: `categoryTree.*`
        // source:
        //
        // ```php
        // [
        //     'categoryTree' => ['cat-1', 'cat-2'],
        // ]
        // ```
        //
        // result:
        //
        // ```php
        // ['cat-1', 'cat-2']
        // ```
        //
        // That flat list is then handed to `writeListValues()` to rebuild the
        // final export payload shape under the selected record path.
        ['prefix' => $prefix, 'suffix' => $suffix] = $this->parseListPath($path) ?? ['prefix' => '', 'suffix' => ''];
        $list = $this->readValue($source, $prefix);
        if (!\is_array($list)) {
            return [];
        }

        $values = [];
        foreach ($list as $item) {
            if ($suffix === '') {
                $value = $item;
            } elseif (\is_array($item)) {
                $value = $this->readValue($item, $suffix);
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
     * Normalizes both supported list path shapes:
     *
     * - `tags.*.name` -> `prefix = tags`, `suffix = name`
     * - `categoryTree.*` -> `prefix = categoryTree`, `suffix = ''`
     *
     * @return array{prefix: string, suffix: string}|null
     */
    private function parseListPath(string $path): ?array
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

    /**
     * Shopware entity `jsonSerialize()` is only shallow here: nested DAL
     * associations such as `tags`, `visibilities`, or `categories` remain
     * JsonSerializable objects until final JSON encoding happens. The same can
     * happen for struct-like values inside custom fields, for example a
     * `CalculatedPrice` object stored below `customFields.exportedPrice`.
     *
     * The export mapper needs a real nested array tree because it reads record
     * paths like `tags.*.id` directly in PHP before any JSON encoding.
     *
     * Example:
     *
     * - before: `['tags' => TagCollection(...)]`
     * - after: `['tags' => [['id' => '...'], ['id' => '...']]]`
     * - before: `['customFields' => ['exportedPrice' => CalculatedPrice(...)]]`
     * - after: `['customFields' => ['exportedPrice' => ['unitPrice' => 10.0, ...]]]`
     */
    private function normalizeSerializedValue(mixed $value): mixed
    {
        if ($value instanceof \JsonSerializable) {
            return $this->normalizeSerializedValue($value->jsonSerialize());
        }

        if (\is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                $value[$key] = $this->normalizeSerializedValue($nestedValue);
            }

            return $value;
        }

        return $value;
    }
}
