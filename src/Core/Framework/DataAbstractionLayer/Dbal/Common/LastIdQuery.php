<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;

class LastIdQuery implements IterableQuery
{
    /**
     * @var QueryBuilder
     */
    private $query;

    /**
     * @var string|null
     */
    private $lastId;

    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    public function fetch(): array
    {
        $data = $this->query->execute()->fetchAll();
        $data = FetchModeHelper::keyPair($data);

        $keys = array_keys($data);
        $this->lastId = array_pop($keys);

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

        return (int) $query->execute()->fetchColumn();
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
