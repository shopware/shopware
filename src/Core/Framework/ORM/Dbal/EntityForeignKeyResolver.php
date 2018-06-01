<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\Struct\Uuid;

/**
 * Determines all associated data for a definition.
 * Used to determines which associated will be deleted to or which associated data would restrict a delete operation.
 */
class EntityForeignKeyResolver
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    public function __construct(Connection $connection, EntityDefinitionQueryHelper $queryHelper)
    {
        $this->connection = $connection;
        $this->queryHelper = $queryHelper;
    }

    /**
     * Returns an associated nested array which contains all affected restrictions of the provided ids.
     * Example:
     *  [
     *      [
     *          'pk' => '43c6baad-7561-40d8-aabb-bca533a8284f'
     *          restrictions => [
     *              'Shopware\Core\Content\Product\ProductDefinition' => [
     *                  '1ffd7ea9-58c6-4355-8256-927aae8efb07',
     *                  '1ffd7ea9-58c6-4355-8256-927aae8efb07'
     *              ]
     *          ]
     *      ]
     *  ]
     *
     * @param EntityDefinition|string $definition
     * @param array                   $ids
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public function getAffectedDeleteRestrictions(string $definition, array $ids, Context $context): array
    {
        return $this->fetch($definition, $ids, RestrictDelete::class, $context);
    }

    /**
     * Returns an associated nested array which contains all affected cascaded delete entities.
     * Example:
     *  [
     *      [
     *          'pk' => '43c6baad-7561-40d8-aabb-bca533a8284f'
     *          restrictions => [
     *              'Shopware\Core\Content\Product\ProductDefinition' => [
     *                  '1ffd7ea9-58c6-4355-8256-927aae8efb07',
     *                  '1ffd7ea9-58c6-4355-8256-927aae8efb07'
     *              ]
     *          ]
     *      ]
     *  ]
     *
     * @param EntityDefinition|string $definition
     * @param array                   $ids
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public function getAffectedDeletes(string $definition, array $ids, Context $context): array
    {
        return $this->fetch($definition, $ids, CascadeDelete::class, $context);
    }

    /**
     * @param EntityDefinition|string $definition
     * @param array                   $ids
     * @param string                  $class
     * @param Context      $context
     *
     * @return array
     */
    private function fetch(string $definition, array $ids, string $class, Context $context): array
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return [];
        }

        if (!$definition::getFields()->has('id')) {
            return [];
        }

        $query = new QueryBuilder($this->connection);

        $root = $definition::getEntityName();
        $rootAlias = EntityDefinitionQueryHelper::escape($definition::getEntityName());

        $query->from(EntityDefinitionQueryHelper::escape($definition::getEntityName()), $rootAlias);
        $query->addSelect($rootAlias . '.id as root');

        $cascades = $definition::getFields()->filterByFlag($class);

        $this->joinCascades($definition, $cascades, $root, $query, $class, $context);

        $this->addWhere($ids, $rootAlias, $query);
        $query->andWhere($rootAlias . '.tenant_id = :tenant');

        $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        $query->setParameter('liveVersion', Uuid::fromStringToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('tenant', Uuid::fromStringToBytes($context->getTenantId()));

        $result = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        return $this->extractValues($definition, $result, $root);
    }

    private function joinCascades(string $definition, FieldCollection $cascades, string $root, QueryBuilder $query, string $class, Context $context): void
    {
        foreach ($cascades as $cascade) {
            $alias = $root . '.' . $cascade->getPropertyName();

            if ($cascade instanceof OneToManyAssociationField) {
                $this->queryHelper->resolveField($cascade, $definition, $root, $query, $context);

                $query->addSelect(
                    'GROUP_CONCAT(HEX(' .
                    EntityDefinitionQueryHelper::escape($alias) . '.id)' .
                    ' SEPARATOR \'||\')  as ' . EntityDefinitionQueryHelper::escape($alias)
                );
            }

            if ($cascade instanceof ManyToManyAssociationField) {
                $mappingAlias = $root . '.' . $cascade->getPropertyName() . '.mapping';

                $this->queryHelper->resolveField($cascade, $definition, $root, $query, $context);

                $query->addSelect(
                    'GROUP_CONCAT(HEX(' .
                    EntityDefinitionQueryHelper::escape($mappingAlias) . '.' . $cascade->getMappingReferenceColumn() .
                    ') SEPARATOR \'||\')  as ' . EntityDefinitionQueryHelper::escape($alias)
                );
                continue;
            }

            if ($cascade instanceof ManyToOneAssociationField) {
                $this->queryHelper->resolveField($cascade, $definition, $root, $query, $context);

                $query->addSelect(
                    'GROUP_CONCAT(HEX(' .
                    EntityDefinitionQueryHelper::escape($alias) . '.id)' .
                    ' SEPARATOR \'||\')  as ' . EntityDefinitionQueryHelper::escape($alias)
                );
            }

            //avoid infinite recursive call
            if ($cascade->getReferenceClass() === $definition) {
                continue;
            }
            $nested = $cascade->getReferenceClass()::getFields()->filterByFlag($class);

            $this->joinCascades($cascade->getReferenceClass(), $nested, $alias, $query, $class, $context);
        }
    }

    private function addWhere(array $ids, string $rootAlias, QueryBuilder $query): void
    {
        $counter = 1;
        $group = null;
        foreach ($ids as $pk) {
            $part = [];
            $group = array_keys($pk);
            foreach ($pk as $key => $value) {
                $param = 'param' . $counter;

                $part[] = sprintf(
                    '%s.%s = :%s',
                    $rootAlias,
                    EntityDefinitionQueryHelper::escape($key),
                    $param
                );

                $query->setParameter($param, Uuid::fromStringToBytes($value));
                ++$counter;
            }
            $query->orWhere(implode(' AND ', $part));
        }

        foreach ($group as $column) {
            $query->addGroupBy($rootAlias . '.' . EntityDefinitionQueryHelper::escape($column));
        }
    }

    private function extractValues(string $definition, array $result, string $root): array
    {
        $mapped = [];

        foreach ($result as $pk => $row) {
            $pk = Uuid::fromBytesToHex($pk);

            $restrictions = [];

            foreach ($row as $key => $value) {
                $value = array_filter(explode('||', (string) $value));
                if (empty($value)) {
                    continue;
                }

                $value = array_map('strtolower', $value);

                $field = $this->queryHelper->getField($key, $definition, $root);

                if (!$field) {
                    throw new \RuntimeException(sprintf('Field by key %s not found', $key));
                }

                /** @var AssociationInterface $field */
                if ($field instanceof ManyToManyAssociationField) {
                    $class = $field->getMappingDefinition();

                    if (!array_key_exists($class, $restrictions)) {
                        $restrictions[$class] = [];
                    }

                    $sourceProperty = $class::getFields()->getByStorageName(
                        $field->getMappingLocalColumn()
                    );
                    $targetProperty = $class::getFields()->getByStorageName(
                        $field->getMappingReferenceColumn()
                    );

                    foreach ($value as $nested) {
                        $restrictions[$class][] = [
                            $sourceProperty->getPropertyName() => $pk,
                            $targetProperty->getPropertyName() => $nested,
                        ];
                    }

                    continue;
                }

                $class = $field->getReferenceClass();

                if (!array_key_exists($class, $restrictions)) {
                    $restrictions[$class] = [];
                }

                $restrictions[$class] = array_merge_recursive($restrictions[$class], $value);
            }

            if (empty($restrictions)) {
                continue;
            }
            $mapped[] = ['pk' => $pk, 'restrictions' => $restrictions];
        }

        return array_values($mapped);
    }
}
