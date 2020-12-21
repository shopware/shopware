<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;

class TreeUpdater
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(DefinitionInstanceRegistry $registry, Connection $connection)
    {
        $this->registry = $registry;
        $this->connection = $connection;
    }

    public function update(string $parentId, string $entity, Context $context): array
    {
        $definition = $this->registry->getByEntityName($entity);

        $parent = $this->loadParents(
            Uuid::fromHexToBytes($parentId),
            $definition,
            Uuid::fromHexToBytes($context->getVersionId())
        );

        if ($parent === null) {
            return [];
        }

        return $this->updateRecursive($parent, $definition, $context);
    }

    private function updateRecursive(array $entity, EntityDefinition $definition, Context $context): array
    {
        $ids[] = $this->updateTree($entity, $definition, $context);
        foreach ($this->getChildren($entity, $definition, $context) as $child) {
            $child['parent'] = $entity;
            $child['parentCount'] = $entity['parentCount'] + 1;
            $ids = array_merge($ids, $this->updateRecursive($child, $definition, $context));
        }

        return $ids;
    }

    private function getChildren(array $parent, EntityDefinition $definition, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition->getEntityName());
        $query->from($escaped);

        $query->select($this->getFieldsToSelect($definition));
        $query->andWhere('parent_id = :id');
        $query->setParameter('id', $parent['id']);
        $this->makeQueryVersionAware($definition, Uuid::fromHexToBytes($context->getVersionId()), $query);

        return $query->execute()->fetchAll();
    }

    private function updateTree(array $entity, EntityDefinition $definition, Context $context): string
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition->getEntityName());
        $query->update($escaped);

        /** @var TreePathField $pathField */
        foreach ($definition->getFields()->filterInstance(TreePathField::class) as $pathField) {
            $path = 'null';

            if (\array_key_exists('parent', $entity)) {
                $path = '"|' . implode('|', $this->buildPathArray($entity['parent'], $pathField)) . '|"';
            }

            $query->set($pathField->getStorageName(), $path);
        }

        /** @var TreeLevelField $field */
        foreach ($definition->getFields()->filterInstance(TreeLevelField::class) as $field) {
            $level = 1;

            if (\array_key_exists('parent', $entity)) {
                $level = $entity['parent']['parentCount'] + 1;
            }

            $query->set($field->getStorageName(), (string) $level);
        }

        $query->andWhere('id = :id');
        $query->setParameter('id', $entity['id']);
        $this->makeQueryVersionAware($definition, Uuid::fromHexToBytes($context->getVersionId()), $query);

        RetryableQuery::retryable(function () use ($query): void {
            $query->execute();
        });

        return Uuid::fromBytesToHex($entity['id']);
    }

    private function buildPathArray(array $parent, TreePathField $field): array
    {
        $path = [];

        if (\array_key_exists('parent', $parent)) {
            $path = $this->buildPathArray($parent['parent'], $field);
        }

        try {
            $path[] = Uuid::fromBytesToHex($parent[$field->getPathField()]);
        } catch (InvalidUuidException | InvalidUuidLengthException $e) {
            $path[] = $parent[$field->getPathField()];
        }

        return $path;
    }

    private function loadParents(string $parentId, EntityDefinition $definition, string $versionId): ?array
    {
        $query = $this->getEntityByIdQuery($parentId, $definition);
        $this->makeQueryVersionAware($definition, $versionId, $query);

        $result = $query->execute()->fetch();

        if ($result === false) {
            return null;
        }

        $result['parentCount'] = 1;

        if ($result['parent_id']) {
            if ($definition->isVersionAware()) {
                $versionId = $result['parent_version_id'];
            }
            $result['parent'] = $this->loadParents($result['parent_id'], $definition, $versionId);
            $result['parentCount'] = $result['parent']['parentCount'] + 1;
        }

        return $result;
    }

    private function getFieldsToSelect(EntityDefinition $definition): array
    {
        $fields = ['id', 'parent_id'];

        if ($definition->isVersionAware()) {
            $fields[] = 'version_id';
            $fields[] = 'parent_version_id';
        }

        $fields = $definition->getFields()
            ->filterInstance(TreePathField::class)
            ->reduce(function (array $fields, TreePathField $field) {
                if (!\in_array($field->getPathField(), $fields, true)) {
                    $fields[] = $field->getPathField();
                }

                return $fields;
            }, $fields);

        return $fields;
    }

    private function makeQueryVersionAware(EntityDefinition $definition, string $versionId, QueryBuilder $query): void
    {
        if ($definition->isVersionAware()) {
            $query->andWhere('version_id = :versionId');
            $query->setParameter('versionId', $versionId);
        }
    }

    private function getEntityByIdQuery(string $parentId, EntityDefinition $definition): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $escaped = EntityDefinitionQueryHelper::escape($definition->getEntityName());

        $query->from($escaped);

        $query->select($this->getFieldsToSelect($definition));
        $query->andWhere('id = :id');
        $query->setParameter('id', $parentId);

        return $query;
    }
}
