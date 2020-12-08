<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

class ChildCountUpdater
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(DefinitionInstanceRegistry $registry, Connection $connection)
    {
        $this->registry = $registry;
        $this->connection = $connection;
    }

    public function update(string $entity, array $parentIds, Context $context): void
    {
        $definition = $this->registry->getByEntityName($entity);

        if (empty($parentIds)) {
            return;
        }

        try {
            // try update all ids with a single sql statement, this works only if no other process writes this table
            $this->trySingleUpdate($definition, $parentIds, $context);
        } catch (DeadlockException $e) {
            // deadlock will appear when another process tries to write the same records
            $this->doMultiUpdate($definition, $parentIds, $context);
        }
    }

    private function trySingleUpdate(EntityDefinition $definition, array $parentIds, Context $context): void
    {
        $entity = $definition->getEntityName();
        $versionAware = $definition->isVersionAware();

        $sql = sprintf(
            'UPDATE #entity#  as parent
                LEFT JOIN
                (
                    SELECT parent_id, count(id) total
                    FROM   #entity#
                    %s
                    GROUP BY parent_id
                ) child ON parent.id = child.parent_id
            SET parent.child_count = IFNULL(child.total, 0)
            WHERE parent.id IN (:ids)
            %s',
            $versionAware ? 'WHERE version_id = :version' : '',
            $versionAware ? 'AND parent.version_id = :version' : ''
        );

        $sql = str_replace(
            ['#entity#'],
            [EntityDefinitionQueryHelper::escape($entity)],
            $sql
        );

        $params = ['ids' => Uuid::fromHexToBytesList($parentIds)];
        if ($versionAware) {
            $params['version'] = Uuid::fromHexToBytes($context->getVersionId());
        }

        $this->connection->executeUpdate($sql, $params, ['ids' => Connection::PARAM_STR_ARRAY]);
    }

    private function doMultiUpdate(EntityDefinition $definition, array $parentIds, Context $context): void
    {
        $entityName = $definition->getEntityName();

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(parent_id)) as id',
            'COUNT(id) as total',
        ]);
        $query->from(EntityDefinitionQueryHelper::escape($entityName), 'parent');
        $query->where('parent.parent_id IN (:ids)');
        $query->andWhere('parent.parent_id IS NOT NULL');
        $query->setParameter('ids', Uuid::fromHexToBytesList($parentIds), Connection::PARAM_STR_ARRAY);

        if ($definition->isVersionAware()) {
            $query->andWhere('parent.version_id = :versionId');
            $query->setParameter('versionId', Uuid::fromHexToBytes($context->getVersionId()));
        }

        $query->groupBy('parent.parent_id');
        $totals = $query->execute()->fetchAll();

        $sql = sprintf('UPDATE %s SET child_count = :count WHERE id = :id', EntityDefinitionQueryHelper::escape($entityName));
        $params = [];
        if ($definition->isVersionAware()) {
            $sql = sprintf('UPDATE %s SET child_count = :count WHERE id = :id AND version_id = :versionId', EntityDefinitionQueryHelper::escape($entityName));
            $params = ['versionId' => Uuid::fromHexToBytes($context->getVersionId())];
        }

        $update = new RetryableQuery($this->connection->prepare($sql));

        $totals = FetchModeHelper::keyPair($totals);

        foreach ($totals as $id => $total) {
            $update->execute(array_merge($params, ['id' => Uuid::fromHexToBytes($id), 'count' => (int) $total]));
        }

        $parentIds = array_flip($parentIds);
        $without = array_diff_key($parentIds, $totals);

        $without = array_keys($without);

        foreach ($without as $id) {
            $update->execute(array_merge($params, ['id' => Uuid::fromHexToBytes($id), 'count' => 0]));
        }
    }
}
