<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class LastIdQuery implements IterableQuery
{
    private ?int $lastId = null;

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

        $keys = array_keys($data);
        $this->lastId = (int) array_pop($keys);

        $this->query->setParameter('lastId', $this->lastId);

        return $data;
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

    public function getOffset(): array
    {
        return ['offset' => $this->lastId];
    }
}
