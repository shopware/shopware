<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Record;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Service\CurrencyCodeProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
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
 * Traversal is definition-aware, not just array-path-based. That allows the
 * builder to keep the current DAL definition in sync while it walks nested
 * associations and to apply export-specific rules exactly where they appear:
 * - `translations.DEFAULT.*` reads from the resolved translated values
 * - `translations.de-DE.*` reads one explicit locale translation
 * - `price.EUR.*` and `price.DEFAULT.*` read one explicit `PriceField` row
 * - `manyToOne.id` prefers the stored foreign-key field like `taxId`
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
        private readonly LanguageLocaleCodeProvider $languageLocaleCodeProvider,
        private readonly CurrencyCodeProvider $currencyCodeProvider
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

        $serializedEntity = $this->normalizeSerializedValue($entity->jsonSerialize());

        \assert(\is_array($serializedEntity));

        $payload = [];
        foreach ($profile->getRecordPaths() as $path) {
            $this->writePayloadValue($payload, $serializedEntity, $definition, $path);
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
     * The resolved value is then written back through `RecordPathWalker`, which
     * rebuilds the normalized export payload tree from the configured paths.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $serializedEntity
     */
    private function writePayloadValue(array &$payload, array $serializedEntity, EntityDefinition $definition, string $path): void
    {
        $value = $this->readExportValue($serializedEntity, $definition, $path);

        if ($value === null || $value === []) {
            return;
        }

        RecordPathWalker::writeValue($payload, $path, $value);
    }

    /**
     * Reads one export value from the serialized entity tree.
     *
     * Traversal is definition-aware so nested associations can still use
     * special handling such as:
     * - `translations.DEFAULT.*` and `translations.de-DE.*`
     * - `price.EUR.*` and `price.DEFAULT.*` for real `PriceField`s
     * - `manyToOne.id`, which prefers the stored foreign-key field like `taxId`
     *
     * The path is split once here and then handled recursively so nested
     * associations, nested translations, and wildcard lists all follow the same
     * traversal model.
     *
     * @param array<string, mixed> $serializedEntity
     */
    private function readExportValue(array $serializedEntity, EntityDefinition $definition, string $path): mixed
    {
        return $this->readExportSegments($serializedEntity, $definition, explode('.', $path));
    }

    /**
     * Recursively traverses one export path while keeping the current DAL
     * definition in sync with the current serialized node.
     *
     * The current `$current` node always belongs to the current `$definition`.
     * When the path enters an association, the traversal switches both to the
     * nested serialized node and to the referenced definition.
     *
     * @param array<int, string> $segments
     */
    private function readExportSegments(mixed $current, EntityDefinition $definition, array $segments): mixed
    {
        if ($segments === []) {
            return $current;
        }

        $segment = array_shift($segments);
        \assert(\is_string($segment));

        if ($segment === '*') {
            if (!\is_array($current)) {
                return null;
            }

            // Wildcard lists are collected item by item so paths like
            // `tags.*.id` and `tags.*.name` can later be merged by index when
            // the normalized export payload is rebuilt.
            $values = [];
            foreach ($current as $item) {
                $value = $this->readExportSegments($item, $definition, $segments);
                if ($value === null) {
                    continue;
                }

                $values[] = $value;
            }

            return $values;
        }

        if (!\is_array($current)) {
            return null;
        }

        if ($segment === 'translations') {
            return $this->readTranslatedSegments($current, $definition, $segments);
        }

        $field = $definition->getField($segment);
        if ($field instanceof ManyToOneAssociationField && $segments === ['id']) {
            $fkField = $definition->getFields()->getByStorageName($field->getStorageName());

            if ($fkField instanceof FkField) {
                return $current[$fkField->getPropertyName()] ?? null;
            }
        }

        if ($field instanceof PriceField && $this->isPriceSelectorSegment($segments[0] ?? null)) {
            return $this->readPriceSegments($current[$segment] ?? null, $segments);
        }

        if (!\array_key_exists($segment, $current)) {
            return null;
        }

        if ($field instanceof AssociationField) {
            $nextDefinition = $this->resolveAssociationDefinition($field);

            if ($nextDefinition !== null) {
                return $this->readExportSegments($current[$segment], $nextDefinition, $segments);
            }
        }

        return $this->readExportSegments($current[$segment], $definition, $segments);
    }

    /**
     * Resolves one `translations.<code>` segment and continues traversal inside
     * the selected translation payload.
     *
     * Example:
     * - `manufacturer.translations.DEFAULT.name`
     *   reads from the resolved translated values first
     * - `manufacturer.translations.de-DE.name`
     *   reads the explicit `de-DE` translation from the loaded association
     *
     * @param array<string, mixed> $serializedEntity
     * @param array<int, string> $segments
     */
    private function readTranslatedSegments(array $serializedEntity, EntityDefinition $definition, array $segments): mixed
    {
        $translationCode = array_shift($segments);
        if (!\is_string($translationCode) || $translationCode === '') {
            return null;
        }

        $translationDefinition = $definition->getTranslationDefinition();

        if ($translationCode === 'DEFAULT') {
            $translated = \is_array($serializedEntity['translated'] ?? null) ? $serializedEntity['translated'] : [];

            $value = $this->readExportSegments($translated, $translationDefinition ?? $definition, $segments);

            if ($value !== null) {
                return $value;
            }

            return $this->readExportSegments($serializedEntity, $definition, $segments);
        }

        $translations = $serializedEntity['translations'] ?? null;
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

            return $this->readExportSegments($translation, $translationDefinition ?? $definition, $segments);
        }

        return null;
    }

    /**
     * Resolves one `price.<currency-code>` segment and continues traversal
     * inside the selected `PriceField` row.
     *
     * Example:
     * - `price.DEFAULT.net`
     *   reads the default-currency price row
     * - `price.EUR.gross`
     *   reads the explicit EUR price row
     *
     * @param array<int, string> $segments
     */
    private function readPriceSegments(mixed $serializedPrice, array $segments): mixed
    {
        if (!\is_array($serializedPrice)) {
            return null;
        }

        $currencyCode = array_shift($segments);
        if (!\is_string($currencyCode) || $currencyCode === '') {
            return null;
        }

        $currencyId = $currencyCode === 'DEFAULT'
            ? Defaults::CURRENCY
            : $this->currencyCodeProvider->getCurrencyIdByCode($currencyCode);

        if ($currencyId === null) {
            return null;
        }

        $priceRow = $this->findPriceRow($serializedPrice, $currencyId);
        if (!\is_array($priceRow)) {
            return null;
        }

        if ($segments === []) {
            return $priceRow;
        }

        return RecordPathWalker::readValue($priceRow, implode('.', $segments));
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

    private function isPriceSelectorSegment(mixed $segment): bool
    {
        if (!\is_string($segment) || $segment === '') {
            return false;
        }

        if ($segment === 'DEFAULT') {
            return true;
        }

        return $this->currencyCodeProvider->hasCurrencyCode($segment);
    }

    private function resolveAssociationDefinition(AssociationField $field): ?EntityDefinition
    {
        if ($field instanceof ManyToOneAssociationField || $field instanceof OneToManyAssociationField) {
            return $field->getReferenceDefinition();
        }

        if ($field instanceof ManyToManyAssociationField) {
            return $field->getToManyReferenceDefinition();
        }

        return null;
    }

    /**
     * `PriceField` values can reach us either as the decoded row list shape
     * (`[['currencyId' => ...], ...]`) or in DB-style keyed form
     * (`['c<currencyId>' => [...]]`). Support both so export paths stay robust
     * regardless of the current serialization shape.
     *
     * @return array<string, mixed>|null
     */
    private function findPriceRow(array $serializedPrice, string $currencyId): ?array
    {
        $storageKey = 'c' . $currencyId;
        $keyedRow = $serializedPrice[$storageKey] ?? $serializedPrice[$currencyId] ?? null;

        if (\is_array($keyedRow)) {
            return $keyedRow;
        }

        foreach ($serializedPrice as $priceRow) {
            if (!\is_array($priceRow)) {
                continue;
            }

            if (($priceRow['currencyId'] ?? null) !== $currencyId) {
                continue;
            }

            return $priceRow;
        }

        return null;
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
