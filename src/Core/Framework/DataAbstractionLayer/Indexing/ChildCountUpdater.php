<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class ChildCountUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $connection
    ) {
    }

    /**
     * @param array<string> $parentIds
     */
    public function update(string $entity, array $parentIds, Context $context): void
    {
        $definition = $this->registry->getByEntityName($entity);

        if ($parentIds === []) {
            return;
        }

        RetryableTransaction::retryable($this->connection, function () use ($definition, $parentIds, $context): void {
            $this->trySingleUpdate($definition, $parentIds, $context);
        });
    }

    /**
     * @param array<string> $parentIds
     */
    private function trySingleUpdate(EntityDefinition $definition, array $parentIds, Context $context): void
    {
        $entity = EntityDefinitionQueryHelper::escape($definition->getEntityName());
        $versionAware = $definition->isVersionAware();

        // sort the ids so concurrent transactions acquire the parent row locks in a consistent order
        $ids = Uuid::fromHexToBytesList($parentIds);
        sort($ids);

        $params = ['ids' => $ids];
        if ($versionAware) {
            $params['version'] = Uuid::fromHexToBytes($context->getVersionId());
        }

        // Read the counts with a plain SELECT (non-locking consistent read) instead of a
        // single self-joined UPDATE: the subquery of the previous statement took shared
        // next-key locks on all child rows while the UPDATE took exclusive locks on the
        // parent rows, which deadlocks under concurrent writes to the same tree (NEXT-22174).
        // A slightly stale count is acceptable - child_count is recomputed on every indexing run.
        $counts = $this->connection->fetchAllKeyValue(
            \sprintf(
                'SELECT parent_id, COUNT(id) FROM %s WHERE parent_id IN (:ids) %s GROUP BY parent_id',
                $entity,
                $versionAware ? 'AND version_id = :version' : ''
            ),
            $params,
            ['ids' => ArrayParameterType::BINARY]
        );

        // the primary key lookup only takes record locks on the listed parents, no gap locks
        $cases = '0';
        if ($counts !== []) {
            $cases = 'CASE id ' . implode('', array_map(
                static fn (int $i) => \sprintf('WHEN :id%d THEN :count%d ', $i, $i),
                range(0, \count($counts) - 1)
            )) . 'ELSE 0 END';

            $i = 0;
            foreach ($counts as $parentId => $total) {
                $params['id' . $i] = $parentId;
                $params['count' . $i] = (int) $total;
                ++$i;
            }
        }

        $this->connection->executeStatement(
            \sprintf(
                'UPDATE %s SET child_count = %s WHERE id IN (:ids) %s',
                $entity,
                $cases,
                $versionAware ? 'AND version_id = :version' : ''
            ),
            $params,
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
