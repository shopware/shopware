<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class LastIdQuery
 */
class LastIdQuery implements IterableQuery
{
    /**
     * @var QueryBuilder
     */
    private $query;

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
        $keys = array_keys($data);
        $this->query->setParameter('lastId', array_pop($keys));

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
