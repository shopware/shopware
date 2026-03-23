<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\Xml\CustomFields;

/**
 * @phpstan-import-type CustomFieldSetArray from \Shopware\Core\System\CustomField\Xml\CustomFieldSet
 */
#[Package('framework')]
class CustomFieldSetPersister
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     * @param EntityRepository<CustomFieldSetRelationCollection> $customFieldSetRelationRepository
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly Connection $connection,
        private readonly EntityRepository $customFieldSetRelationRepository,
        private readonly EntityRepository $customFieldRepository,
    ) {
    }

    /**
     * Sync custom field sets from parsed XML definition.
     *
     * When $appId is provided, existing sets are looked up by app_id (app behavior).
     * When $appId is null, existing sets are looked up by the names defined in the XML (plugin behavior).
     */
    public function sync(CustomFields $customFields, ?string $appId, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $innerContext) use ($customFields, $appId): void {
            $this->upsertCustomFieldSets($customFields, $appId, $innerContext);
        });
    }

    /**
     * Remove custom field sets by their names.
     *
     * @param list<string> $setNames
     */
    public function removeByNames(array $setNames, Context $context): void
    {
        if ($setNames === []) {
            return;
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $innerContext) use ($setNames): void {
            $ids = $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(id)) FROM custom_field_set WHERE name IN (:names)',
                ['names' => $setNames],
                ['names' => ArrayParameterType::STRING]
            );

            if ($ids === []) {
                return;
            }

            $this->customFieldSetRepository->delete(
                array_map(static fn (string $id): array => ['id' => $id], $ids),
                $innerContext
            );
        });
    }

    private function upsertCustomFieldSets(CustomFields $customFields, ?string $appId, Context $context): void
    {
        $existingCustomFieldSets = $this->getExistingCustomFieldSets($customFields, $appId);

        if ($customFields->getCustomFieldSets() === []) {
            if ($existingCustomFieldSets !== []) {
                $this->deleteObsoleteIds(
                    array_values($existingCustomFieldSets),
                    [],
                    [],
                    $context
                );
            }

            return;
        }

        $payload = [];
        $obsoleteRelations = [];
        $obsoleteFields = [];

        foreach ($customFields->getCustomFieldSets() as $customFieldSet) {
            if (!\array_key_exists($customFieldSet->getName(), $existingCustomFieldSets)) {
                $existingRelations = $existingFields = [];
                $payload[] = $customFieldSet->toEntityArray($appId, $existingRelations, $existingFields);

                continue;
            }

            $customFieldSetId = $existingCustomFieldSets[$customFieldSet->getName()];

            $existingRelations = Uuid::fromBytesToHexList(
                $this->connection->fetchAllKeyValue(
                    'SELECT entity_name, id FROM custom_field_set_relation WHERE set_id = :setId',
                    ['setId' => Uuid::fromHexToBytes($customFieldSetId)]
                )
            );
            $existingFields = Uuid::fromBytesToHexList(
                $this->connection->fetchAllKeyValue(
                    'SELECT name, id FROM custom_field WHERE set_id = :setId',
                    ['setId' => Uuid::fromHexToBytes($customFieldSetId)]
                )
            );
            $entityData = $customFieldSet->toEntityArray($appId, $existingRelations, $existingFields, $customFieldSetId);

            $obsoleteRelations = array_merge($obsoleteRelations, array_values($existingRelations));
            $obsoleteFields = array_merge($obsoleteFields, array_values($existingFields));

            $payload[] = $entityData;
            unset($existingCustomFieldSets[$customFieldSet->getName()]);
        }

        $this->deleteObsoleteIds(
            array_values($existingCustomFieldSets),
            $obsoleteRelations,
            $obsoleteFields,
            $context
        );

        $this->customFieldSetRepository->upsert($payload, $context);
    }

    /**
     * @return array<string, string> Map of set name => set id (hex)
     */
    private function getExistingCustomFieldSets(CustomFields $customFields, ?string $appId): array
    {
        if ($appId !== null) {
            // App behavior: look up by app_id
            /** @var array<string, string> $allCustomFields */
            $allCustomFields = $this->connection->fetchAllKeyValue(
                'SELECT id, name FROM custom_field_set WHERE app_id = :appId',
                ['appId' => Uuid::fromHexToBytes($appId)]
            );

            $groupedByName = [];
            foreach ($allCustomFields as $id => $name) {
                $groupedByName[$name][] = Uuid::fromBytesToHex($id);
            }

            $existingCustomFieldSets = [];
            foreach ($groupedByName as $name => $ids) {
                if (\count($ids) > 1) {
                    // duplicate sets - delete all and let them be recreated
                    $this->deleteObsoleteIds($ids, [], [], Context::createDefaultContext());
                } else {
                    $existingCustomFieldSets[$name] = $ids[0];
                }
            }

            return $existingCustomFieldSets;
        }

        // Plugin behavior: look up by names defined in XML
        $setNames = array_map(
            static fn ($set) => $set->getName(),
            $customFields->getCustomFieldSets()
        );

        if ($setNames === []) {
            return [];
        }

        /** @var array<string, string> $rows */
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT name, LOWER(HEX(id)) FROM custom_field_set WHERE name IN (:names)',
            ['names' => $setNames],
            ['names' => ArrayParameterType::STRING]
        );

        return $rows;
    }

    /**
     * @param list<string> $obsoleteFieldSets
     * @param list<string> $obsoleteRelations
     * @param list<string> $obsoleteFields
     */
    private function deleteObsoleteIds(array $obsoleteFieldSets, array $obsoleteRelations, array $obsoleteFields, Context $context): void
    {
        if ($obsoleteFieldSets !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $obsoleteFieldSets);

            $this->customFieldSetRepository->delete($ids, $context);
        }

        if ($obsoleteRelations !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $obsoleteRelations);

            $this->customFieldSetRelationRepository->delete($ids, $context);
        }

        if ($obsoleteFields !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $obsoleteFields);

            $this->customFieldRepository->delete($ids, $context);
        }
    }
}
