<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFields;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class CustomFieldPersister
{
    /**
     * @param EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly Connection $connection
    ) {
    }

    /**
     * @internal only for use by the app-system
     */
    public function updateCustomFields(Manifest $manifest, string $appId, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($manifest, $appId): void {
            $obsoleteIds = $this->upsertCustomFieldSets($manifest->getCustomFields(), $appId, $context);
            $this->deleteObsoleteIds($obsoleteIds, $context);
        });
    }

    /**
     * @param list<string> $obsoleteIds
     */
    private function deleteObsoleteIds(array $obsoleteIds, Context $context): void
    {
        if (!empty($obsoleteIds)) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $obsoleteIds);

            $this->customFieldSetRepository->delete($ids, $context);
        }
    }

    /**
     * @return list<string> the obsolete custom field sets that need to be deleted
     */
    private function upsertCustomFieldSets(?CustomFields $customFields, string $appId, Context $context): array
    {
        $existingCustomFieldSets = Uuid::fromBytesToHexList(
            $this->connection->fetchAllKeyValue(
                'SELECT name, id FROM custom_field_set WHERE app_id = :appId',
                ['appId' => Uuid::fromHexToBytes($appId)]
            )
        );

        if (!$customFields || empty($customFields->getCustomFieldSets())) {
            return array_values($existingCustomFieldSets);
        }

        $payload = [];

        foreach ($customFields->getCustomFieldSets() as $customFieldSet) {
            if (!\array_key_exists($customFieldSet->getName(), $existingCustomFieldSets)) {
                $payload[] = $customFieldSet->toEntityArray($appId, []);

                continue;
            }

            $existingRelations = Uuid::fromBytesToHexList(
                $this->connection->fetchAllKeyValue(
                    'SELECT entity_name, id FROM custom_field_set_relation WHERE set_id = :setId',
                    ['setId' => Uuid::fromHexToBytes($existingCustomFieldSets[$customFieldSet->getName()])]
                )
            );
            $entityData = $customFieldSet->toEntityArray($appId, $existingRelations);
            $entityData['id'] = $existingCustomFieldSets[$customFieldSet->getName()];

            $payload[] = $entityData;
            unset($existingCustomFieldSets[$customFieldSet->getName()]);
        }

        $this->customFieldSetRepository->upsert($payload, $context);

        return array_values($existingCustomFieldSets);
    }
}
