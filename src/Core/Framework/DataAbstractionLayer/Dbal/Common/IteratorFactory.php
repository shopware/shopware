<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @final
 */
#[Package('framework')]
class IteratorFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry
    ) {
    }

    /**
     * @param array{offset: int|null}|null $lastId
     */
    public function createIterator(string|EntityDefinition $definition, ?array $lastId = null, int $limit = 50, ?string $versionId = null): IterableQuery
    {
        if (\is_string($definition)) {
            $definition = $this->registry->getByEntityName($definition);
        }

        $entity = $definition->getEntityName();

        $escaped = EntityDefinitionQueryHelper::escape($entity);
        $query = new QueryBuilder($this->connection);
        $query->from($escaped);
        $query->setMaxResults($limit);

        if ($definition->isVersionAware() && $versionId !== null) {
            $query->andWhere($escaped . '.version_id = :versionId');
            $query->setParameter('versionId', Uuid::fromHexToBytes($versionId));
        }

        if ($definition->hasAutoIncrement()) {
            $query->select($escaped . '.auto_increment', 'LOWER(HEX(' . $escaped . '.id)) as id');
            $query->andWhere($escaped . '.auto_increment > :lastId');
            $query->addOrderBy($escaped . '.auto_increment');
            $query->setParameter('lastId', 0);

            if ($lastId !== null) {
                $query->setParameter('lastId', $lastId['offset']);
            }

            return new LastIdQuery($query);
        }

        $query->setFirstResult(0);
        if ($lastId !== null) {
            $query->setFirstResult((int) $lastId['offset']);
        }

        $columns = [];
        foreach ($definition->getPrimaryKeys() as $field) {
            if ($field instanceof StorageAware && !$field instanceof VersionField && !$field instanceof ReferenceVersionField) {
                $columns[$field->getPropertyName()] = $escaped . '.' . EntityDefinitionQueryHelper::escape($field->getStorageName());
            }
        }

        if (\count($columns) === 1) {
            [$column] = array_values($columns);
            $query->select($column, 'LOWER(HEX(' . $column . '))');

            return new OffsetQuery($query);
        }

        // definitions with several primary keys, like translations, are iterated by their keys without the version,
        // each key has the property names of its fields like the primary keys of written entities
        foreach ($columns as $property => $column) {
            $query->addSelect('LOWER(HEX(' . $column . ')) AS ' . EntityDefinitionQueryHelper::escape($property));
        }

        return new OffsetQuery($query->distinct(), fetchKeys: true);
    }
}
