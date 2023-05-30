<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class OffsetQuery implements IterableQuery
{
    private int $offset = 0;

    public function __construct(private readonly QueryBuilder $query)
    {
    }

    public function fetch(): array
    {
        $data = $this->query->executeQuery()->fetchAllKeyValue();

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

        //get first column for distinct selection
        $select = $query->getQueryPart('select');

        $query->resetQueryPart('orderBy');
        $query->select('COUNT(DISTINCT ' . array_shift($select) . ')');

        return (int) $query->executeQuery()->fetchOne();
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }
}
