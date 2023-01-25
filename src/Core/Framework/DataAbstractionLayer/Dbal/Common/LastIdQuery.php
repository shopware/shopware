<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class LastIdQuery implements IterableQuery
{
    private ?int $lastId = null;

    public function __construct(private readonly QueryBuilder $query)
    {
    }

    public function fetch(): array
    {
        $data = $this->query->executeQuery()->fetchAllKeyValue();

        $keys = array_keys($data);
        $this->lastId = (int) array_pop($keys);

        $this->query->setParameter('lastId', $this->lastId);

        return $data;
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

    public function getOffset(): array
    {
        return ['offset' => $this->lastId];
    }
}
