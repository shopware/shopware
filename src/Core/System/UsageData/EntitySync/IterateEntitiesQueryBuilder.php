<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\UsageDataException;

/**
 * @internal
 */
#[Package('data-services')]
class IterateEntitiesQueryBuilder
{
    public function __construct(
        private readonly EntityDefinitionService $entityDefinitionService,
        private readonly Connection $connection,
        private readonly int $batchSize,
    ) {
    }

    public function create(
        string $entityName,
        Operation $operation,
        \DateTimeImmutable $currentRun,
        \DateTimeInterface $lastApprovalDate,
        ?\DateTimeImmutable $lastRun,
    ): QueryBuilder {
        $definition = $this->entityDefinitionService->getAllowedEntityDefinition($entityName);

        if ($definition === null) {
            // we can throw here as this should not happen as we will only dispatch messages for allowed entities
            throw UsageDataException::entityNotAllowed($entityName);
        }

        return match ($operation) {
            Operation::CREATE => $this->createQueryForCreatedEntities($definition, $lastApprovalDate, $lastRun),
            Operation::UPDATE => $this->createQueryForUpdatedEntities($definition, $lastApprovalDate, $lastRun),
            Operation::DELETE => $this->createQueryForDeletedEntities($definition, $currentRun, $lastRun),
        };
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }

    private function createQueryForCreatedEntities(
        EntityDefinition $definition,
        \DateTimeInterface $lastApprovalDate,
        ?\DateTimeImmutable $lastRun,
    ): QueryBuilder {
        if ($lastRun === null) {
            return $this->getBaseQuery($definition);
        }

        $iterableQuery = $this->getBaseQuery($definition);
        $iterableQuery->andWhere(
            'created_at > :lastRun',
            'created_at <= :lastApprovalDate',
        );

        $iterableQuery->andWhere(
            CompositeExpression::or(
                $iterableQuery->expr()->isNull('updated_at'),
                $iterableQuery->expr()->lte('updated_at', ':lastApprovalDate')
            )
        );

        $iterableQuery->setParameter('lastRun', $lastRun->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $iterableQuery->setParameter('lastApprovalDate', $lastApprovalDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        return $iterableQuery;
    }

    private function createQueryForUpdatedEntities(
        EntityDefinition $definition,
        \DateTimeInterface $lastApprovalDate,
        ?\DateTimeImmutable $lastRun,
    ): QueryBuilder {
        if ($lastRun === null) {
            throw UsageDataException::unexpectedOperationInInitialRun(Operation::UPDATE);
        }

        $iterableQuery = $this->getBaseQuery($definition);
        $iterableQuery->andWhere(
            'created_at <= :lastRun',
            'updated_at > :lastRun',
            'updated_at <= :lastApprovalDate',
        );

        $iterableQuery->setParameter('lastRun', $lastRun->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $iterableQuery->setParameter('lastApprovalDate', $lastApprovalDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        return $iterableQuery;
    }

    private function createQueryForDeletedEntities(
        EntityDefinition $definition,
        \DateTimeInterface $currentRunDate,
        ?\DateTimeImmutable $lastRun,
    ): QueryBuilder {
        if ($lastRun === null) {
            throw UsageDataException::unexpectedOperationInInitialRun(Operation::DELETE);
        }

        $entityName = $definition->getEntityName();
        $escapedIdFieldStorageName = EntityDefinitionQueryHelper::escape('id');

        $query = $this->createQueryBuilder();
        $query->setTitle("UsageData EntitySync - iterate entity deletions for '$entityName'");
        $query->select(sprintf(
            'LOWER(HEX(%s)) as %s',
            $escapedIdFieldStorageName,
            $escapedIdFieldStorageName,
        ));
        $query->from(EntityDefinitionQueryHelper::escape('usage_data_entity_deletion'));
        $query->where($query->expr()->eq(EntityDefinitionQueryHelper::escape('entity_name'), ':entityName'));
        $query->andWhere($query->expr()->lte(EntityDefinitionQueryHelper::escape('deleted_at'), ':currentRunDate'));
        $query->setParameter('entityName', $entityName);
        $query->setParameter('currentRunDate', $currentRunDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $query->setMaxResults($this->batchSize);

        return $query;
    }

    private function getBaseQuery(EntityDefinition $definition): QueryBuilder
    {
        $entityName = $definition->getEntityName();
        $escapedEntityName = EntityDefinitionQueryHelper::escape($entityName);

        $primaryKeys = $definition->getPrimaryKeys();
        $selections = [];

        foreach ($primaryKeys as $primaryKey) {
            if (!$primaryKey instanceof StorageAware) {
                continue;
            }

            if ($primaryKey instanceof VersionField) {
                continue;
            }

            if ($primaryKey instanceof ReferenceVersionField) {
                continue;
            }

            $escapedFieldStorageName = EntityDefinitionQueryHelper::escape($primaryKey->getStorageName());

            $selections[] = sprintf(
                'LOWER(HEX(%s.%s)) as %s',
                $escapedEntityName,
                $escapedFieldStorageName,
                $escapedFieldStorageName,
            );
        }

        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->setTitle("UsageData EntitySync - iterate entities for '$entityName'");
        $queryBuilder->from($escapedEntityName);
        $queryBuilder->select(...$selections);
        $queryBuilder->setMaxResults($this->batchSize);
        $queryBuilder->setFirstResult(0);

        $queryBuilder = $this->checkLiveVersion($definition, $queryBuilder);

        return $queryBuilder;
    }

    private function checkLiveVersion(EntityDefinition $definition, QueryBuilder $queryBuilder): QueryBuilder
    {
        $hasVersionFields = false;

        foreach ($definition->getFields() as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                $hasVersionFields = true;
                $queryBuilder->andWhere(
                    sprintf('%s = :versionId', EntityDefinitionQueryHelper::escape($field->getStorageName())),
                );
            }
        }

        if ($hasVersionFields) {
            $queryBuilder->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        }

        return $queryBuilder;
    }
}
