<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

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

    public function createIterator(EntityDefinition $definition): IterableQuery
    {
        $entity = $definition->getEntityName();

        $escaped = EntityDefinitionQueryHelper::escape($entity);
        $query = $this->connection->createQueryBuilder();
        $query->from($escaped);
        $query->setMaxResults(50);

        $query->select('LOWER(HEX(' . $escaped . '.id))');
        $query->orderBy($escaped . '.id', 'asc');
        $query->setFirstResult(0);

        return new OffsetQuery($query);
    }
}
