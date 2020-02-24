<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
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

        $entityName = $definition->getEntityName();
        if (empty($parentIds)) {
            return;
        }

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

        $update = new RetryableQuery($this->connection->prepare($sql));

        $totals = FetchModeHelper::keyPair($totals);

        foreach ($totals as $id => $total) {
            $update->execute(['id' => Uuid::fromHexToBytes($id), 'count' => (int) $total]);
        }

        $parentIds = array_flip($parentIds);
        $without = array_diff_key($parentIds, $totals);

        $without = array_keys($without);

        foreach ($without as $id) {
            $update->execute(['id' => Uuid::fromHexToBytes($id), 'count' => 0]);
        }
    }
}
