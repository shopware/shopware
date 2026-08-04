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
 * @internal
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
     * When $appId is null, existing sets are looked up by extension_name (plugin behavior),
     * which also catches sets that were removed from the XML so they can be deleted.
     */
    public function sync(CustomFields $customFields, ?string $appId, ?string $extensionName, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $innerContext) use ($customFields, $appId, $extensionName): void {
            $this->upsertCustomFieldSets($customFields, $appId, $extensionName, $innerContext);
        });
    }

    private function upsertCustomFieldSets(CustomFields $customFields, ?string $appId, ?string $extensionName, Context $context): void
    {
        $existingCustomFieldSets = $this->getExistingCustomFieldSets($appId, $extensionName, $context);

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

        // Read the relations and fields of all known sets up front instead of two queries per set
        $relationsBySet = $this->fetchGroupedBySet(
            'SELECT LOWER(HEX(set_id)) AS setId, entity_name AS `key`, id FROM custom_field_set_relation WHERE set_id IN (:ids)',
            array_values($existingCustomFieldSets)
        );
        $fieldsBySet = $this->fetchGroupedBySet(
            'SELECT LOWER(HEX(set_id)) AS setId, name AS `key`, id FROM custom_field WHERE set_id IN (:ids)',
            array_values($existingCustomFieldSets)
        );

        foreach ($customFields->getCustomFieldSets() as $customFieldSet) {
            if (!\array_key_exists($customFieldSet->getName(), $existingCustomFieldSets)) {
                $existingRelations = $existingFields = [];
                $entityData = $customFieldSet->toEntityArray($appId, $existingRelations, $existingFields);
                if ($extensionName !== null) {
                    $entityData['extensionName'] = $extensionName;
                }

                $payload[] = $entityData;

                continue;
            }

            $customFieldSetId = $existingCustomFieldSets[$customFieldSet->getName()];

            $existingRelations = $relationsBySet[$customFieldSetId] ?? [];
            $existingFields = $fieldsBySet[$customFieldSetId] ?? [];
            $entityData = $customFieldSet->toEntityArray($appId, $existingRelations, $existingFields, $customFieldSetId);
            if ($extensionName !== null) {
                $entityData['extensionName'] = $extensionName;
            }

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
    private function getExistingCustomFieldSets(?string $appId, ?string $extensionName, Context $context): array
    {
        if ($appId !== null) {
            // App behavior: look up by app_id
            /** @var array<string, string> $allCustomFields */
            $allCustomFields = $this->connection->fetchAllKeyValue(
                'SELECT id, name FROM custom_field_set WHERE app_id = :appId',
                ['appId' => Uuid::fromHexToBytes($appId)]
            );
        } elseif ($extensionName !== null) {
            // Plugin behavior: look up all sets owned by the extension, so that sets which
            // were removed from the XML are loaded too and can be deleted.
            /** @var array<string, string> $allCustomFields */
            $allCustomFields = $this->connection->fetchAllKeyValue(
                'SELECT id, name FROM custom_field_set WHERE extension_name = :extensionName',
                ['extensionName' => $extensionName]
            );
        } else {
            return [];
        }

        $groupedByName = [];
        foreach ($allCustomFields as $id => $name) {
            $groupedByName[$name][] = Uuid::fromBytesToHex($id);
        }

        $existingCustomFieldSets = [];
        foreach ($groupedByName as $name => $ids) {
            if (\count($ids) > 1) {
                // duplicate sets - delete all and let them be recreated
                $this->deleteObsoleteIds($ids, [], [], $context);
            } else {
                $existingCustomFieldSets[$name] = $ids[0];
            }
        }

        return $existingCustomFieldSets;
    }

    /**
     * Reads rows of `setId`, `key` and `id` for all given custom field set ids at once.
     *
     * @param list<string> $customFieldSetIds
     *
     * @return array<string, array<string, string>> the hex ids, keyed by custom field set id and row key
     */
    private function fetchGroupedBySet(string $query, array $customFieldSetIds): array
    {
        if ($customFieldSetIds === []) {
            return [];
        }

        $grouped = [];
        $rows = $this->connection->fetchAllAssociative(
            $query,
            ['ids' => Uuid::fromHexToBytesList($customFieldSetIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        foreach ($rows as $row) {
            $grouped[(string) $row['setId']][(string) $row['key']] = Uuid::fromBytesToHex((string) $row['id']);
        }

        return $grouped;
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
