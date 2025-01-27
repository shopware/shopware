<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class OffsetQuery implements IterableQuery
{
    private int $offset = 0;

    /**
     * @param QueryBuilder $query - @deprecated tag:v6.7.0 - Parameter type will be changed to `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder`
     */
    public function __construct(private readonly QueryBuilder $query)
    {
        if (!$query instanceof \Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Parameter $query must be an instance of \Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder');
        }
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

        // get first column for distinct selection
        $select = $query->getQueryPart('select');

        $query->resetOrderBy();
        $query->select('COUNT(DISTINCT ' . array_shift($select) . ')');

        return (int) $query->executeQuery()->fetchOne();
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Return type will be changed to `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder`
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }
}
