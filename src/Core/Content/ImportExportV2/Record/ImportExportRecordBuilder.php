<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Record;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Locale\LocaleException;

/**
 * Projects one loaded DAL entity into the shared `ImportExportRecord` shape.
 *
 * The profile declares the export contract through `recordPaths`. This builder
 * reads exactly those paths from the serialized entity tree and rebuilds them
 * into one normalized payload array that both JSON and CSV writers can consume.
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
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly LanguageLocaleCodeProvider $languageLocaleCodeProvider
    ) {
    }

    /**
     * Main export entrypoint for one root entity.
     *
     * It serializes the entity into a plain nested array, copies the configured
     * `recordPaths`, and wraps the result in one `ImportExportRecord`.
     *
     * Example result:
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
     * Copies one configured `recordPath` from the serialized entity into the
     * normalized export payload.
     *
     * Missing values are skipped so profiles behave like "export when present",
     * not "every path must always exist".
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $serialized
     */
    private function writePayloadValue(array &$payload, array $serialized, EntityDefinition $definition, string $path): void
    {
        $value = $this->readExportValue($serialized, $definition, $path);

        if ($value === null || $value === []) {
            return;
        }

        RecordPathWalker::writeValue($payload, $path, $value);
    }

    /**
     * Reads one export value from the serialized entity tree.
     *
     * Most paths are handled generically through `RecordPathWalker`. The two
     * special cases are:
     * - `translations.DEFAULT.*` and `translations.de-DE.*`
     * - `manyToOne.id`, which prefers the stored foreign-key field like `taxId`
     *
     * @param array<string, mixed> $serialized
     */
    private function readExportValue(array $serialized, EntityDefinition $definition, string $path): mixed
    {
        if (str_starts_with($path, 'translations.')) {
            return $this->readTranslationValue($serialized, $path);
        }

        $segments = explode('.', $path);
        $topLevel = $segments[0] ?? '';

        $field = $definition->getField($topLevel);
        if ($field instanceof ManyToOneAssociationField && \count($segments) === 2 && $segments[1] === 'id') {
            $fkField = $definition->getFields()->getByStorageName($field->getStorageName());

            if ($fkField instanceof FkField) {
                return $serialized[$fkField->getPropertyName()] ?? RecordPathWalker::readValue($serialized, $path);
            }
        }

        return RecordPathWalker::readValue($serialized, $path);
    }

    /**
     * Resolves profile translation paths in two ways:
     * - `translations.DEFAULT.*` reads the currently resolved translated value
     * - `translations.de-DE.*` reads the explicitly loaded translation entry
     *
     * @param array<string, mixed> $serialized
     */
    private function readTranslationValue(array $serialized, string $path): mixed
    {
        $segments = explode('.', $path, 3);

        $translationCode = $segments[1] ?? null;
        $fieldPath = $segments[2] ?? null;

        if (!\is_string($translationCode) || !\is_string($fieldPath) || $fieldPath === '') {
            return null;
        }

        if ($translationCode === 'DEFAULT') {
            return RecordPathWalker::readValue(\is_array($serialized['translated'] ?? null) ? $serialized['translated'] : [], $fieldPath)
                ?? RecordPathWalker::readValue($serialized, $fieldPath);
        }

        $translations = $serialized['translations'] ?? null;
        if (!\is_array($translations)) {
            return null;
        }

        foreach ($translations as $translationKey => $translation) {
            if (!\is_array($translation)) {
                continue;
            }

            $languageId = $this->resolveTranslationLanguageId($translationKey, $translation);
            if ($languageId === null) {
                continue;
            }

            try {
                $localeCode = $this->languageLocaleCodeProvider->getLocaleForLanguageId($languageId);
            } catch (LocaleException) {
                continue;
            }

            if (mb_strtolower($localeCode) !== mb_strtolower($translationCode)) {
                continue;
            }

            return RecordPathWalker::readValue($translation, $fieldPath);
        }

        return null;
    }

    /**
     * Translation collections are usually keyed by language id, but we prefer
     * the serialized `languageId` field when it is present because that works
     * regardless of the collection key shape.
     *
     * @param array<string, mixed> $translation
     */
    private function resolveTranslationLanguageId(int|string $translationKey, array $translation): ?string
    {
        $languageId = $translation['languageId'] ?? null;
        if (\is_string($languageId) && $languageId !== '') {
            return $languageId;
        }

        if (!\is_string($translationKey) || $translationKey === '') {
            return null;
        }

        try {
            $this->languageLocaleCodeProvider->getLocaleForLanguageId($translationKey);
        } catch (LocaleException) {
            return null;
        }

        return $translationKey;
    }

    /**
     * Recursively normalizes `jsonSerialize()` output into plain PHP arrays and
     * scalars.
     *
     * Shopware serializes many nested values as `JsonSerializable` objects,
     * for example association collections or struct-like custom-field values.
     * The path-based export mapper needs a real array tree before it can read
     * paths like `tags.*.id` in PHP.
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
