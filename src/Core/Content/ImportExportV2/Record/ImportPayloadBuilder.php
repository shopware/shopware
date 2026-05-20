<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Record;

use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * Builds one DAL write payload from one normalized `ImportExportRecord`.
 *
 * This is the reverse direction of `ImportExportRecordBuilder`: it takes the
 * shared record payload produced by a reader and converts it into the field
 * names and nesting that DAL `upsert()` expects.
 *
 * The profile still drives the shape through `recordPaths`, so only explicitly
 * allowed paths are copied into the final write payload.
 *
 * Traversal is definition-aware, not just array-path-based. That allows the
 * builder to keep the current DAL definition in sync while it walks nested
 * associations and to apply import-specific rules exactly where they appear:
 * - `translations.DEFAULT.*` becomes the system language id
 * - locale-code translations like `translations.de-DE.*` are kept because DAL already normalizes them
 * - `manyToOne.id` becomes the foreign-key field like `taxId`
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
     * Main import mapping entrypoint for one record.
     */
    public function build(ImportExportRecord $record, ImportExportV2ProfileEntity $profile): array
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($profile->getEntity());

        $payload = [];

        if (isset($record->payload['id']) && \is_string($record->payload['id']) && $record->payload['id'] !== '') {
            // `matchBy` resolves an existing root entity before import and
            // injects its id into the normalized record payload. Preserving it
            // here turns the final DAL write into an update instead of a create.
            $payload['id'] = $record->payload['id'];
        }

        foreach ($profile->getRecordPaths() as $path) {
            $this->writePayloadValue($payload, $record->payload, $definition, $path);
        }

        return $payload;
    }

    /**
     * Copies one configured `recordPath` from the shared import record into the
     * final DAL write payload.
     *
     * The path is split once here and then handled recursively so nested
     * associations, nested translations, and wildcard lists all follow the same
     * traversal model.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $recordPayload
     */
    private function writePayloadValue(array &$payload, array $recordPayload, EntityDefinition $definition, string $path): void
    {
        $this->writePayloadSegments($payload, $recordPayload, $definition, explode('.', $path));
    }

    /**
     * Recursively traverses one normalized import record path while keeping the
     * current DAL definition in sync with the current source node.
     *
     * This mirrors the export-side traversal in `ImportExportRecordBuilder`, so
     * nested associations can still use special handling such as:
     * - `translations.DEFAULT.*`, which becomes the system language id
     * - `translations.de-DE.*`, which is left as a locale-code key for DAL
     * - `manyToOne.id`, which becomes the DAL foreign-key field like `taxId`
     *
     * The current `$source` node always belongs to the current `$definition`.
     * When the path enters an association, the traversal switches both to the
     * nested source node and to the referenced definition.
     *
     * @param array<string, mixed> $target
     * @param array<int, string> $segments
     */
    private function writePayloadSegments(array &$target, mixed $source, EntityDefinition $definition, array $segments): void
    {
        if ($segments === []) {
            return;
        }

        $segment = array_shift($segments);
        \assert(\is_string($segment));

        if ($segment === '*') {
            if (!\is_array($source)) {
                return;
            }

            // Wildcard lists merge by index so sibling paths like `tags.*.id`
            // and `tags.*.name` rebuild one DAL list of objects instead of two
            // independent lists.
            foreach ($source as $index => $item) {
                if ($item === null || $item === []) {
                    continue;
                }

                if ($segments === []) {
                    $target[$index] = $item;

                    continue;
                }

                $nestedTarget = isset($target[$index]) && \is_array($target[$index]) ? $target[$index] : [];
                $this->writePayloadSegments($nestedTarget, $item, $definition, $segments);

                if ($nestedTarget !== []) {
                    $target[$index] = $nestedTarget;
                }
            }

            $target = array_values($target);

            return;
        }

        if (!\is_array($source) || !\array_key_exists($segment, $source)) {
            return;
        }

        $value = $source[$segment];
        if ($value === null || $value === []) {
            return;
        }

        if ($segment === 'translations') {
            $this->writeTranslatedSegments($target, $value, $definition, $segments);

            return;
        }

        $field = $definition->getFields()->get($segment);
        if ($field instanceof ManyToOneAssociationField && $segments === ['id']) {
            $fkField = $definition->getFields()->getByStorageName($field->getStorageName());

            if ($fkField instanceof FkField && \is_array($value)) {
                $fkValue = $value['id'] ?? null;

                if ($fkValue !== null && $fkValue !== []) {
                    $target[$fkField->getPropertyName()] = $fkValue;
                }
            }

            return;
        }

        if ($segments === []) {
            $target[$segment] = $value;

            return;
        }

        if (!\is_array($value)) {
            return;
        }

        if ($field instanceof AssociationField) {
            $nextDefinition = $this->resolveAssociationDefinition($field);

            if ($nextDefinition !== null) {
                $nestedTarget = isset($target[$segment]) && \is_array($target[$segment]) ? $target[$segment] : [];
                $this->writePayloadSegments($nestedTarget, $value, $nextDefinition, $segments);

                if ($nestedTarget !== []) {
                    $target[$segment] = $nestedTarget;
                }

                return;
            }
        }

        $nestedTarget = isset($target[$segment]) && \is_array($target[$segment]) ? $target[$segment] : [];
        $this->writePayloadSegments($nestedTarget, $value, $definition, $segments);

        if ($nestedTarget !== []) {
            $target[$segment] = $nestedTarget;
        }
    }

    /**
     * Rewrites one `translations.<code>` segment into the write payload shape
     * DAL expects and then continues traversal inside the selected translation.
     *
     * `DEFAULT` is rewritten to the system language id, while locale-code keys
     * like `de-DE` are kept as-is because DAL already normalizes them.
     *
     * Example:
     * - `manufacturer.translations.DEFAULT.name`
     *   becomes `manufacturer.translations.<system-language-id>.name`
     * - `manufacturer.translations.de-DE.name`
     *   stays `manufacturer.translations.de-DE.name`
     *
     * @param array<string, mixed> $target
     * @param array<int, string> $segments
     */
    private function writeTranslatedSegments(array &$target, mixed $source, EntityDefinition $definition, array $segments): void
    {
        if (!\is_array($source)) {
            return;
        }

        $translationCode = array_shift($segments);
        if (!\is_string($translationCode) || $translationCode === '' || !\array_key_exists($translationCode, $source)) {
            return;
        }

        $translationTargetKey = $translationCode === 'DEFAULT' ? Defaults::LANGUAGE_SYSTEM : $translationCode;
        $translationSource = $source[$translationCode];

        if ($translationSource === null || $translationSource === []) {
            return;
        }

        $translations = isset($target['translations']) && \is_array($target['translations']) ? $target['translations'] : [];

        if ($segments === []) {
            $translations[$translationTargetKey] = $translationSource;
            $target['translations'] = $translations;

            return;
        }

        if (!\is_array($translationSource)) {
            return;
        }

        $translationDefinition = $definition->getTranslationDefinition() ?? $definition;

        $translationTarget = isset($translations[$translationTargetKey]) && \is_array($translations[$translationTargetKey]) ? $translations[$translationTargetKey] : [];

        $this->writePayloadSegments($translationTarget, $translationSource, $translationDefinition, $segments);

        if ($translationTarget === []) {
            return;
        }

        $translations[$translationTargetKey] = $translationTarget;
        $target['translations'] = $translations;
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
}
