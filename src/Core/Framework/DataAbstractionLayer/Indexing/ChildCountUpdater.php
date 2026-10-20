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

        // lock the parents (PK only) so concurrent recalculations serialise instead of a stale count
        // overwriting a fresh one; no child rows are locked, keeping the deadlock-free footprint
        $this->connection->executeStatement(
            \sprintf(
                'SELECT id FROM %s WHERE id IN (:ids) %s FOR UPDATE',
                $entity,
                $versionAware ? 'AND version_id = :version' : ''
            ),
            $params,
            ['ids' => ArrayParameterType::BINARY]
        );

        $aggregations = $this->connection->fetchAllKeyValue(
            \sprintf(
                'SELECT parent_id, COUNT(id) FROM %s WHERE parent_id IN (:ids) %s GROUP BY parent_id',
                $entity,
                $versionAware ? 'AND version_id = :version' : ''
            ),
            $params,
            ['ids' => ArrayParameterType::BINARY]
        );
        /**
         * @var list<array{
         *     sql: non-falsy-string,
         *     params: non-empty-array<lowercase-string&non-falsy-string, int|non-empty-string>
         * }> $cases
         */
        $cases = array_map(
            static fn (string $id, int $key): array => [
                'sql' => \sprintf('WHEN :id%d THEN :count%d ', $key, $key),
                'params' => ['id' . $key => $id, 'count' . $key => (int) ($aggregations[$id] ?? 0)],
            ],
            $ids,
            array_keys($ids)
        );

        $params = array_merge($params, ...array_column($cases, 'params'));

        $this->connection->executeStatement(
            \sprintf(
                'UPDATE %s SET child_count = CASE id %s ELSE 0 END WHERE id IN (:ids) %s',
                $entity,
                implode('', array_column($cases, 'sql')),
                $versionAware ? 'AND version_id = :version' : ''
            ),
            $params,
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
