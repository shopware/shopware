<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;

class OffsetQuery implements IterableQuery
{
    /**
     * @var QueryBuilder
     */
    private $query;

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @param QueryBuilder $query
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    public function fetch(): array
    {
        $data = $this->query->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);
        $this->offset += count($data);
        $this->query->setFirstResult($this->offset);

        return $data;
    }

    public function fetchCount(): int
    {
        /** @var $query QueryBuilder */
        $query = clone $this->query;

        //get first column for distinct selection
        $select = $query->getQueryPart('select');

        $query->resetQueryPart('orderBy');
        $query->select('COUNT(DISTINCT ' . array_shift($select) . ')');

        return (int) $query->execute()->fetch(\PDO::FETCH_COLUMN);
    }
}
