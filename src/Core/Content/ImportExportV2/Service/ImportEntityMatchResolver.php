<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Content\ImportExportV2\Profile\ImportExportV2ProfileEntity;
use Shopware\Core\Content\ImportExportV2\Record\ImportExportRecord;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves existing root entity ids for one import chunk based on one root
 * string `matchBy` field.
 *
 * This is intentionally narrow:
 * - it only matches the root entity of the current profile
 * - it only supports one root string `matchBy` field such as `productNumber`
 * - missing match values simply mean "treat this as a create"
 *
 * Example:
 * - profile `matchBy = 'productNumber'`
 * - one import chunk contains:
 *   - `['productNumber' => 'SW10001']`
 *   - `['productNumber' => 'SW10002']`
 *
 * Result:
 * - one DAL lookup using `EqualsAnyFilter('productNumber', [...])`
 * - existing product ids are mapped back by `productNumber`
 * - `null` for records with no match or incomplete match values
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportEntityMatchResolver
{
    public function __construct(private readonly DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
    }

    /**
     * @param list<ImportExportRecord> $records
     */
    public function resolveAll(array $records, ImportExportV2ProfileEntity $profile, Context $context): void
    {
        $matchPath = $profile->getMatchBy();
        if ($matchPath === null || $matchPath === '') {
            return;
        }

        if (str_contains($matchPath, '*') || str_contains($matchPath, '.')) {
            throw ImportExportV2Exception::invalidImportRecord(\sprintf(
                'matchBy field "%s" must be one root string field.',
                $matchPath
            ));
        }

        $recordIndexesByValue = [];
        $values = [];

        foreach ($records as $index => $record) {
            if (isset($record->payload['id']) && \is_string($record->payload['id']) && $record->payload['id'] !== '') {
                continue;
            }

            $value = $record->payload[$matchPath] ?? null;
            if (!\is_string($value) || $value === '') {
                continue;
            }

            $values[$value] = $value;
            $recordIndexesByValue[$value][] = $index;
        }

        if ($values === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter($matchPath, array_values($values)));

        $repository = $this->definitionInstanceRegistry->getRepository($profile->getEntity());
        $entities = $repository->search($criteria, $context)->getEntities();

        $entityIdsByValue = [];
        foreach ($entities as $entity) {
            $value = $entity->get($matchPath);
            if (!\is_string($value) || $value === '') {
                continue;
            }

            $entityIdsByValue[$value][] = $entity->getUniqueIdentifier();
        }

        foreach ($recordIndexesByValue as $valueKey => $indexes) {
            $matchedIds = array_values(array_unique($entityIdsByValue[$valueKey] ?? []));
            if (\count($matchedIds) > 1) {
                // This should not happen if the profile is well-defined, but we should still guard against it
                // TODO: this can stop the import of the whole chunk, we should somehow return these and put them into the failed records instead
                throw ImportExportV2Exception::invalidImportRecord(\sprintf(
                    'matchBy for profile "%s" matched multiple %s entities.',
                    $profile->getTechnicalName(),
                    $profile->getEntity()
                ));
            }

            $matchedId = $matchedIds[0] ?? null;
            foreach ($indexes as $index) {
                if ($matchedId !== null) {
                    // TODO: is it correct that this is always `id`? What if the entity has a different primary key?
                    // Or we can say import/export only supports root entities with `id` as primary key, which is the common case anyway
                    $records[$index]->payload['id'] = $matchedId;
                }
            }
        }
    }
}
