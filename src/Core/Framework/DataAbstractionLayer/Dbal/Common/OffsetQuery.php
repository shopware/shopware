<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class OffsetQuery implements IterableQuery
{
    private int $offset = 0;

    /**
     * @param bool $fetchKeys the query selects the columns of composite primary keys, each row is returned as a key
     */
    public function __construct(
        private readonly QueryBuilder $query,
        private readonly bool $fetchKeys = false
    ) {
    }

    public function fetch(): array
    {
        $result = $this->query->executeQuery();
        $data = $this->fetchKeys ? $result->fetchAllAssociative() : $result->fetchAllKeyValue();

        $this->offset = $this->query->getFirstResult() + \count($data);
        $this->query->setFirstResult($this->offset);

        return $data;
    }

    public function getOffset(): array
    {
        return ['offset' => $this->offset];
    }

    public function fetchCount(): int
    {
        $query = clone $this->query;

        // get first column for distinct selection, a key is distinct by all its columns, which are counted without their alias
        $select = $query->getSelectParts();
        $columns = $this->fetchKeys ? array_map(static fn (string $column): string => explode(' AS ', $column)[0], $select) : [array_shift($select)];

        $query->resetOrderBy();
        $query->select('COUNT(DISTINCT ' . implode(', ', $columns) . ')');

        return (int) $query->executeQuery()->fetchOne();
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }
}
