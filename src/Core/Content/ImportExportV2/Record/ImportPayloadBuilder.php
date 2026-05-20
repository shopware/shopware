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
 * Builds one DAL write payload from one normalized `ImportExportRecord`.
 *
 * This is the reverse direction of `ImportExportRecordBuilder`: it takes the
 * shared record payload produced by a reader and converts it into the field
 * names and nesting that DAL `upsert()` expects.
 *
 * The profile still drives the shape through `recordPaths`, so only explicitly
 * allowed paths are copied into the final write payload.
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
            // injects its id into the shared record payload. Keeping that id in
            // the DAL payload turns the write into an update instead of a create.
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
     * Most paths can be written generically through `RecordPathWalker`. The two
     * special cases are:
     * - `translations.DEFAULT.*`, which becomes the system language id
     * - `translations.de-DE.*`, which is left as a locale-code key and later
     *   normalized by DAL itself
     * - `manyToOne.id`, which becomes the DAL foreign-key field like `taxId`
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $recordPayload
     */
    private function writePayloadValue(array &$payload, array $recordPayload, EntityDefinition $definition, string $path): void
    {
        $value = RecordPathWalker::readValue($recordPayload, $path);
        if ($value === null || $value === []) {
            return;
        }

        if (str_starts_with($path, 'translations.DEFAULT.')) {
            $path = 'translations.' . Defaults::LANGUAGE_SYSTEM . '.' . substr($path, \strlen('translations.DEFAULT.'));
        }

        $segments = explode('.', $path);
        $topLevel = $segments[0] ?? '';

        $field = $definition->getField($topLevel);
        if ($field instanceof ManyToOneAssociationField && \count($segments) === 2 && $segments[1] === 'id') {
            $fkField = $definition->getFields()->getByStorageName($field->getStorageName());
            if ($fkField instanceof FkField) {
                $payload[$fkField->getPropertyName()] = $value;

                return;
            }
        }

        RecordPathWalker::writeValue($payload, $path, $value);
    }
}
