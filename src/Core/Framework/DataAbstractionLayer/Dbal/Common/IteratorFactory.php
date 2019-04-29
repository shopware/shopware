<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;

class IteratorFactory
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function createIterator(string $definition): IterableQuery
    {
        $entity = $definition::getEntityName();

        $escaped = EntityDefinitionQueryHelper::escape($entity);
        $query = $this->connection->createQueryBuilder();
        $query->from($escaped);
        $query->setMaxResults(50);

        if ($definition::getFields()->has('autoIncrement')) {
            $query->select([$escaped . '.auto_increment', 'LOWER(HEX(' . $escaped . '.id))']);
            $query->andWhere($escaped . '.auto_increment > :lastId');
            $query->addOrderBy($escaped . '.auto_increment');
            $query->setParameter('lastId', 0);

            return new LastIdQuery($query);
        }

        $query->select([$escaped . '.id', 'LOWER(HEX(' . $escaped . '.id))']);
        $query->setFirstResult(0);

        return new OffsetQuery($query);
    }
}
