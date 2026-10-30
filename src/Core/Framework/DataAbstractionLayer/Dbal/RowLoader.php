<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class RowLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<array<string, string>> $primaryKeys in storage format
     * @param list<string> $columns all columns when empty
     *
     * @return list<array<string, mixed>>
     */
    public function load(string $entity, array $primaryKeys, array $columns = []): array
    {
        if ($primaryKeys === []) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();

        if ($columns === []) {
            $query->addSelect('*');
        } else {
            $query->addSelect(...array_map(EntityDefinitionQueryHelper::escape(...), $columns));
        }

        $query->from(EntityDefinitionQueryHelper::escape($entity));

        $this->addPrimaryCondition($query, $primaryKeys);

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param list<array<string, string>> $primaryKeys
     */
    private function addPrimaryCondition(QueryBuilder $query, array $primaryKeys): void
    {
        $all = [];
        $i = 0;
        foreach ($primaryKeys as $primaryKey) {
            $where = [];

            foreach ($primaryKey as $field => $value) {
                ++$i;
                $field = EntityDefinitionQueryHelper::escape($field);
                $where[] = $field . ' = :param' . $i;
                $query->setParameter('param' . $i, $value);
            }

            $all[] = implode(' AND ', $where);
        }

        $query->andWhere(implode(' OR ', $all));
    }
}
