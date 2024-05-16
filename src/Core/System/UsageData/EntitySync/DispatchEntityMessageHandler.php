<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\ManyToManyAssociationService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * @internal
 */
#[AsMessageHandler(handles: DispatchEntityMessage::class)]
#[Package('data-services')]
final class DispatchEntityMessageHandler
{
    public function __construct(
        private readonly EntityDefinitionService $entityDefinitionService,
        private readonly ManyToManyAssociationService $manyToManyAssociationService,
        private readonly UsageDataAllowListService $usageDataAllowListService,
        private readonly Connection $connection,
        private readonly EntityDispatcher $entityDispatcher,
        private readonly ConsentService $consentService,
        private readonly ShopIdProvider $shopIdProvider
    ) {
    }

    public function __invoke(DispatchEntityMessage $message): void
    {
        $definition = $this->entityDefinitionService->getAllowedEntityDefinition($message->entityName);
        if ($definition === null) {
            self::throwUnrecoverableMessageHandlingException($message, 'No allowed entity definition found');
        }

        /** @var EntityDefinition $definition */
        // don't dispatch entity data if shopId is different; handle old messages without shopId
        if ($message->shopId !== null && $this->shopIdProvider->getShopId() !== $message->shopId) {
            self::throwUnrecoverableMessageHandlingException($message, 'Message dispatched for old shopId');
        }

        $lastApprovalDate = $this->consentService->getLastConsentIsAcceptedDate();
        if ($lastApprovalDate === null) {
            self::throwUnrecoverableMessageHandlingException($message, 'No approval date found');
        }

        if ($message->operation === Operation::DELETE) {
            $this->handleDelete($message, $definition);

            return;
        }

        $this->handleUpserts($message, $definition, $lastApprovalDate);
    }

    /**
     * @param array<string, mixed> $entity
     *
     * @return array<string, mixed>
     */
    public static function serialize(FieldCollection $fields, array $entity): array
    {
        $encoded = [];

        foreach ($fields as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            if ($field instanceof ManyToManyIdField) {
                $encoded[$field->getAssociationName()] = $field->getSerializer()->decode($field, $entity[$field->getStorageName()]);

                continue;
            }

            if ($field instanceof BlobField) {
                $serialized = $field->getSerializer()->decode($field, $entity[$field->getStorageName()]);

                $encoded[$field->getPropertyName()] = base64_encode($serialized);

                continue;
            }

            $encoded[$field->getPropertyName()] = $field->getSerializer()->decode($field, $entity[$field->getStorageName()]);
        }

        if (\array_key_exists(DispatchEntitiesQueryBuilder::PUID_FIELD_NAME, $entity)) {
            $encoded[DispatchEntitiesQueryBuilder::PUID_FIELD_NAME] = $entity[DispatchEntitiesQueryBuilder::PUID_FIELD_NAME];
        }

        return $encoded;
    }

    /**
     * @return never-return
     */
    private function throwUnrecoverableMessageHandlingException(DispatchEntityMessage $message, string $errorMessage): void
    {
        throw new UnrecoverableMessageHandlingException(sprintf(
            '%s. Skipping dispatching of entity sync message. Entity: %s, Operation: %s',
            $errorMessage,
            $message->entityName,
            $message->operation->value,
        ));
    }

    private function handleDelete(DispatchEntityMessage $message, EntityDefinition $definition): void
    {
        $rowIds = [];
        foreach ($message->primaryKeys as $pks) {
            $rowIds[] = Uuid::fromHexToBytes($pks['id']);
        }

        $entityName = $definition->getEntityName();

        $qb = new QueryBuilder($this->connection);
        $qb->setTitle("UsageData EntitySync - dispatch entity deletions for '$entityName'");
        $qb->select(EntityDefinitionQueryHelper::escape('entity_ids'))
            ->from(EntityDefinitionQueryHelper::escape('usage_data_entity_deletion'))
            ->where($qb->expr()->in(EntityDefinitionQueryHelper::escape('id'), ':ids'))
            ->setParameter('ids', $rowIds, ArrayParameterType::STRING);

        $queryResult = $qb->executeQuery()->fetchAllAssociative();

        $entityIds = [];
        foreach ($queryResult as $row) {
            $entityIds[] = json_decode($row['entity_ids'], true, flags: \JSON_THROW_ON_ERROR);
        }

        $this->entityDispatcher->dispatch(
            $message->entityName,
            $entityIds,
            $message->operation,
            $message->runDate,
            $message->shopId ?? $this->shopIdProvider->getShopId()
        );

        $qb = new QueryBuilder($this->connection);
        $qb->setTitle("UsageData EntitySync - remove entity deletions for '$entityName'");
        $qb->delete(EntityDefinitionQueryHelper::escape('usage_data_entity_deletion'))
            ->where($qb->expr()->in(EntityDefinitionQueryHelper::escape('id'), ':ids'))
            ->setParameter('ids', $rowIds, ArrayParameterType::STRING);

        $qb->executeStatement();
    }

    private function handleUpserts(
        DispatchEntityMessage $message,
        EntityDefinition $definition,
        \DateTimeImmutable $lastApprovalDate,
    ): void {
        $fields = $this->usageDataAllowListService->getFieldsToSelectFromDefinition($definition);
        $manyToManyAssociationIdFields = $this->entityDefinitionService->getManyToManyAssociationIdFields($fields);

        $missingIdFields = [];
        foreach ($manyToManyAssociationIdFields as $data) {
            if (($idField = $data['idField']) !== null) {
                $fields->add($idField);
            } else {
                $missingIdFields[] = $data['associationField'];
            }
        }

        $primaryKeys = $message->primaryKeys;
        $primaryKeyColumns = array_keys($primaryKeys[0]);
        if (!empty($missingIdFields) && \count($primaryKeyColumns) > 1) {
            self::throwUnrecoverableMessageHandlingException($message, 'Entity sync does not support composite primary keys');
        }

        $primaryKeyName = $primaryKeyColumns[0];

        $mappingIds = $this->manyToManyAssociationService->getMappingIdsForAssociationFields(
            $missingIdFields,
            $primaryKeys,
            $primaryKeyName,
        );

        $queryBuilder = (new DispatchEntitiesQueryBuilder($this->connection))
            ->forEntity($definition->getEntityName())
            ->withFields($fields)
            ->withLastApprovalDateConstraint($message, $lastApprovalDate)
            ->withPrimaryKeys($primaryKeys);

        if ($this->entityDefinitionService->isPuidEntity($definition)) {
            $queryBuilder->withPersonalUniqueIdentifier();
        }

        $queryBuilder->checkLiveVersion($definition);

        $entities = $queryBuilder->execute()->iterateAssociative();

        $serializedEntities = [];
        foreach ($entities as $entity) {
            $entityId = $entity[$primaryKeyName];
            $serializedEntity = self::serialize($fields, $entity);

            foreach ($mappingIds as $associationName => $associationIdsByEntityId) {
                $serializedEntity[$associationName] = [];
                if (\array_key_exists($entityId, $associationIdsByEntityId)) {
                    $serializedEntity[$associationName] = $associationIdsByEntityId[$entityId];
                }
            }

            $serializedEntities[] = $serializedEntity;
        }

        if (empty($serializedEntities)) {
            return;
        }

        $this->entityDispatcher->dispatch(
            $definition->getEntityName(),
            $serializedEntities,
            $message->operation,
            $message->runDate,
            $message->shopId ?? $this->shopIdProvider->getShopId()
        );
    }
}
