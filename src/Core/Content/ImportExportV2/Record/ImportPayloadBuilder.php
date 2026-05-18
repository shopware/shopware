<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Record;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * Builds one DAL-friendly write payload from one shared import/export record.
 *
 * This is the reverse direction of the current export builder:
 * - export: DAL entity -> ImportExportRecord
 * - import: ImportExportRecord -> DAL write payload
 *
 * The profile still drives the shape through `recordPaths`, so the builder only
 * copies values that the profile explicitly allows.
 *
 * Example record payload:
 *
 * ```php
 * [
 *     'productNumber' => 'SW10001',
 *     'active' => true,
 *     'tax' => ['id' => 'tax-123'],
 *     'tags' => [
 *         ['id' => 'tag-1', 'name' => 'Featured'],
 *         ['id' => 'tag-2', 'name' => 'Sale'],
 *     ],
 *     'categoryTree' => ['cat-1', 'cat-2'],
 *     'customFields' => [
 *         'exportedPrice' => [
 *             'unitPrice' => 10.0,
 *         ],
 *     ],
 * ]
 * ```
 *
 * Example write payload:
 *
 * ```php
 * [
 *     'productNumber' => 'SW10001',
 *     'active' => true,
 *     'taxId' => 'tax-123',
 *     'tags' => [
 *         ['id' => 'tag-1', 'name' => 'Featured'],
 *         ['id' => 'tag-2', 'name' => 'Sale'],
 *     ],
 *     'categoryTree' => ['cat-1', 'cat-2'],
 *     'customFields' => [
 *         'exportedPrice' => [
 *             'unitPrice' => 10.0,
 *         ],
 *     ],
 * ]
 * ```
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportPayloadBuilder
{
    public function __construct(private readonly DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
    }

    /**
     * Builds one DAL write payload from one import/export record.
     */
    public function build(ImportExportRecord $record, ImportExportV2ProfileEntity $profile): array
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($profile->getEntity());

        $payload = [];

        if (isset($record->payload['id']) && \is_string($record->payload['id']) && $record->payload['id'] !== '') {
            // `matchBy` injects the resolved root entity id into the mutable
            // record payload. Preserve it here so DAL upsert performs an update
            // instead of trying to create a new root entity.
            // TODO: is it correct that this is always `id`? What if the entity has a different primary key?
            // Or we can say import/export only supports root entities with `id` as primary key, which is the common case anyway
            $payload['id'] = $record->payload['id'];
        }

        foreach ($profile->getRecordPaths() as $path) {
            $this->writePayloadValue($payload, $record->payload, $definition, $path);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $recordPayload
     */
    private function writePayloadValue(array &$payload, array $recordPayload, EntityDefinition $definition, string $path): void
    {
        if ($this->parseListPath($path) !== null) {
            $values = $this->readListValues($recordPayload, $path);
            if ($values !== []) {
                $this->writeListValues($payload, $path, $values);
            }

            return;
        }

        $value = $this->readValue($recordPayload, $path);
        if ($value === null) {
            return;
        }

        $this->writeScalarValue($payload, $definition, $path, $value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeScalarValue(array &$payload, EntityDefinition $definition, string $path, mixed $value): void
    {
        if (str_starts_with($path, 'translations.DEFAULT.')) {
            $path = 'translations.' . Defaults::LANGUAGE_SYSTEM . '.' . substr($path, \strlen('translations.DEFAULT.'));
        }

        $segments = explode('.', $path);
        $topLevel = $segments[0] ?? '';

        $field = $definition->getField($topLevel);
        if ($field instanceof ManyToOneAssociationField && \count($segments) === 2 && $segments[1] === 'id') {
            $fkField = $definition->getFields()->getByStorageName($field->getStorageName());
            if ($fkField instanceof FkField) {
                // Reverse of export `taxId -> tax.id`: for import we prefer the
                // DAL foreign key field directly whenever the record path is a
                // simple many-to-one id reference.
                $payload[$fkField->getPropertyName()] = $value;

                return;
            }
        }

        $this->writeValue($payload, $path, $value);
    }

    /**
     * @param array<string, mixed> $target
     */
    private function writeValue(array &$target, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = &$target;

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
     * Supports both:
     *
     * - `association.*.field`
     * - `plainListField.*`
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
}
