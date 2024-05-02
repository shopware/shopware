<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class EntityWriteResultFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $connection
    ) {
    }

    /**
     * @return array<string, list<EntityWriteResult>>
     */
    public function build(WriteCommandQueue $queue): array
    {
        return $this->buildQueueResults($queue);
    }

    /**
     * @param array<array<string, string>> $ids
     *
     * @return array<string, array<string>>
     */
    public function resolveDelete(EntityDefinition $definition, array $ids): array
    {
        // resolves mapping relations, inheritance and sub domain entities
        return $this->resolveParents($definition, $ids, true);
    }

    /**
     * @param array<string, array<string, mixed>> $rawData
     *
     * @return array<string, array<string>>
     */
    public function resolveWrite(EntityDefinition $definition, array $rawData): array
    {
        // resolve domain parent (order_delivery > order | product_price > product),
        // relations for mapping entities (product_category)
        // and product inheritance (product.parent_id)
        return $this->resolveParents($definition, $rawData);
    }

    /**
     * @param array<string, list<EntityWriteResult>> $results
     *
     * @return array<string, array<string>>
     */
    public function resolveMappings(array $results): array
    {
        $mappings = [];

        foreach ($results as $entity => $result) {
            $definition = $this->registry->getByEntityName($entity);

            if (!$definition instanceof MappingEntityDefinition) {
                continue;
            }

            $ids = array_map(fn (EntityWriteResult $result) => $result->getPrimaryKey(), $result);

            if (empty($ids)) {
                continue;
            }

            $fkFields = $definition->getFields()->filterInstance(FkField::class);
            if ($fkFields->count() <= 0) {
                continue;
            }

            foreach ($fkFields as $field) {
                if (!$field instanceof FkField) {
                    continue;
                }
                $reference = $field->getReferenceDefinition()->getEntityName();

                $mappings[$reference] = array_merge($mappings[$reference] ?? [], array_column($ids, $field->getPropertyName()));
            }
        }

        return $mappings;
    }

    /**
     * @param array<string, array<EntityWriteResult>> $writeResults
     * @param array<string, array<string>|array<array<string, string>>> $parents
     *
     * @return array<string, array<EntityWriteResult>>
     */
    public function addParentResults(array $writeResults, array $parents): array
    {
        foreach ($parents as $entity => $primaryKeys) {
            $primaryKeys = array_unique($primaryKeys);
            if (!isset($writeResults[$entity])) {
                $writeResults[$entity] = [];
            }

            foreach ($primaryKeys as $primaryKey) {
                if ($this->hasResult($entity, $primaryKey, $writeResults)) {
                    continue;
                }
                $writeResults[$entity][] = new EntityWriteResult($primaryKey, [], $entity, EntityWriteResult::OPERATION_UPDATE);
            }
        }

        return $writeResults;
    }

    /**
     * @param array<string, array<EntityWriteResult>> $identifiers
     * @param array<string, list<EntityWriteResult>> $notFound
     * @param array<string, array<string>|array<array<string, string>>> $parents
     */
    public function addDeleteResults(array $identifiers, array $notFound, array $parents): WriteResult
    {
        $results = $this->splitResultsByOperation($identifiers);

        $deleted = $this->addParentResults($results['deleted'], $parents);

        $mapped = [];
        $updates = [];
        foreach ($deleted as $entity => $nested) {
            foreach ($nested as $result) {
                if ($result->getOperation() === EntityWriteResult::OPERATION_UPDATE) {
                    $updates[$entity][] = $result;
                } else {
                    $mapped[$entity][] = $result;
                }
            }
        }

        $updates = array_merge_recursive($results['updated'], $updates);

        return new WriteResult($mapped, $notFound, array_filter($updates));
    }

    /**
     * @param array<array<string, string>> $ids
     *
     * @return array<string, array<string>>
     */
    private function resolveParents(EntityDefinition $definition, array $ids, bool $delete = false): array
    {
        if ($definition instanceof MappingEntityDefinition) {
            // case for mapping entities like (product_category, etc), to trigger indexing for both entities (product and category)
            return $this->resolveMappingParents($definition, $ids);
        }

        $parentIds = [];

        // we only fetch the parent ids if we are inside a delete operation, in this case we want to provide the parent ids as update event
        if ($delete && $definition->isInheritanceAware()) {
            // inheritance case for products (resolve product.parent_id here to trigger indexing for parent)
            $parentIds = $this->fetchParentIds($definition, $ids);
        }

        $parent = $definition->getParentDefinition();

        // is sub entity (like product_price, order_line_item, etc)
        if (!$parent) {
            return $parentIds;
        }

        $fkField = $definition->getFields()->filter(function (Field $field) use ($parent) {
            if (!$field instanceof FkField || $field instanceof ReferenceVersionField) {
                return false;
            }

            return $field->getReferenceDefinition()->getEntityName() === $parent->getEntityName();
        });

        // find foreign key field for parent definition (product_price.product_id in case of product_price provided)
        $fkField = $fkField->first();
        if (!$fkField instanceof FkField) {
            throw DataAbstractionLayerException::missingParentForeignKey($parent->getEntityName());
        }

        $primaryKeys = $this->getPrimaryKeysOfFkField($definition, $ids, $fkField);

        $mapped = array_map(fn ($id) => ['id' => $id], $primaryKeys);

        // recursion call for nested sub entities (order_delivery_position > order_delivery > order)
        $nested = $this->resolveParents($parent, $mapped);

        $entity = $parent->getEntityName();

        $nested[$entity] = array_merge($nested[$entity] ?? [], $primaryKeys, $parentIds[$entity] ?? []);

        return $nested;
    }

    /**
     * @param array<string, array<EntityWriteResult>> $identifiers
     *
     * @return array{deleted: array<string, array<EntityWriteResult>>, updated: array<string, array<EntityWriteResult>>}
     */
    private function splitResultsByOperation(array $identifiers): array
    {
        $deleted = [];
        $updated = [];
        foreach ($identifiers as $entityName => $writeResults) {
            $deletedEntities = array_filter($writeResults, fn (EntityWriteResult $result): bool => $result->getOperation() === EntityWriteResult::OPERATION_DELETE);
            if (!empty($deletedEntities)) {
                $deleted[$entityName] = $deletedEntities;
            }

            $updatedEntities = array_filter($writeResults, fn (EntityWriteResult $result): bool => \in_array($result->getOperation(), [EntityWriteResult::OPERATION_INSERT, EntityWriteResult::OPERATION_UPDATE], true));

            if (!empty($updatedEntities)) {
                $updated[$entityName] = $updatedEntities;
            }
        }

        return ['deleted' => $deleted, 'updated' => $updated];
    }

    /**
     * @param array<array<string, string>> $rawData
     *
     * @return array<array<string>>
     */
    private function resolveMappingParents(EntityDefinition $definition, array $rawData): array
    {
        $fkFields = $definition->getFields()->filter(fn (Field $field) => $field instanceof FkField && !$field instanceof ReferenceVersionField);

        $mapping = [];

        foreach ($fkFields as $fkField) {
            if (!$fkField instanceof FkField) {
                continue;
            }
            $primaryKeys = $this->getPrimaryKeysOfFkField($definition, $rawData, $fkField);

            $entity = $fkField->getReferenceDefinition()->getEntityName();
            $mapping[$entity] = array_merge($mapping[$entity] ?? [], $primaryKeys);

            $mapped = array_map(fn ($id) => ['id' => $id], $primaryKeys);

            // after resolving the mapping entities - we resolve the parent for related entity (maybe inherited for products, or sub domain entities)
            $nested = $this->resolveParents($fkField->getReferenceDefinition(), $mapped);

            foreach ($nested as $entity => $primaryKeys) {
                $mapping[$entity] = array_merge($mapping[$entity] ?? [], $primaryKeys);
            }
        }

        return $mapping;
    }

    /**
     * @param array<array<string, string>> $rawData
     *
     * @return array<string, array<string>>
     */
    private function fetchParentIds(EntityDefinition $definition, array $rawData): array
    {
        $fetchQuery = sprintf(
            'SELECT DISTINCT LOWER(HEX(parent_id)) as id FROM %s WHERE id IN (:ids)',
            EntityDefinitionQueryHelper::escape($definition->getEntityName())
        );

        $parentIds = $this->connection->fetchAllAssociative(
            $fetchQuery,
            ['ids' => Uuid::fromHexToBytesList(array_column($rawData, 'id'))],
            ['ids' => ArrayParameterType::BINARY]
        );

        $ids = array_unique(array_filter(array_column($parentIds, 'id')));

        if (\count($ids) === 0) {
            return [];
        }

        return [$definition->getEntityName() => $ids];
    }

    /**
     * @param array<string, string>|string $primaryKey
     * @param array<string, array<EntityWriteResult>> $results
     */
    private function hasResult(string $entity, string|array $primaryKey, array $results): bool
    {
        if (!isset($results[$entity])) {
            return false;
        }

        foreach ($results[$entity] as $result) {
            if ($result->getPrimaryKey() === $primaryKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<EntityWriteResult>>
     */
    private function buildQueueResults(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        $order = [];
        // we have to create the written events in the written order, otherwise the version manager would
        // trace the change sets in a wrong order
        foreach ($queue->getCommandsInOrder($this->registry) as $command) {
            $class = $command->getEntityName();
            if (isset($order[$class])) {
                continue;
            }
            $order[$class] = $this->registry->getByEntityName($class);
        }

        foreach ($order as $class => $definition) {
            $commands = $queue->getCommands()[$class];

            if (\count($commands) === 0) {
                continue;
            }

            $primaryKeys = $definition->getPrimaryKeys()
                ->filter(static fn (Field $field) => !$field instanceof VersionField && !$field instanceof ReferenceVersionField);

            $identifiers[$definition->getEntityName()] = [];

            $jsonUpdateCommands = [];
            $writeResults = [];

            foreach ($commands as $command) {
                $primaryKey = $this->getCommandPrimaryKey($command, $primaryKeys);
                $uniqueId = \is_array($primaryKey) ? implode('-', $primaryKey) : $primaryKey;

                if ($command instanceof JsonUpdateCommand) {
                    $jsonUpdateCommands[$uniqueId] = $command;

                    continue;
                }

                $operation = EntityWriteResult::OPERATION_UPDATE;
                if ($command instanceof InsertCommand) {
                    $operation = EntityWriteResult::OPERATION_INSERT;
                } elseif ($command instanceof DeleteCommand) {
                    $operation = EntityWriteResult::OPERATION_DELETE;
                }

                $payload = $this->getCommandPayload($command);

                $writeResults[$uniqueId] = new EntityWriteResult(
                    $primaryKey,
                    \array_merge($payload, ($writeResults[$uniqueId] ?? null)?->getPayload() ?? []),
                    $definition->getEntityName(),
                    $operation,
                    $command->getEntityExistence(),
                    $command instanceof ChangeSetAware ? $command->getChangeSet() : null
                );
            }

            /*
             * Updates for entities with attributes are split into two commands: an UpdateCommand and a JsonUpdateCommand.
             * We need to merge the payloads here.
             */
            foreach ($jsonUpdateCommands as $uniqueId => $command) {
                $payload = [];
                if (isset($writeResults[$uniqueId])) {
                    $payload = $writeResults[$uniqueId]->getPayload();
                }

                $definition = $this->registry->getByEntityName($command->getEntityName());

                $field = $definition->getFields()->getByStorageName($command->getStorageName());

                if (!$field instanceof Field) {
                    throw DataAbstractionLayerException::fieldByStorageNameNotFound(
                        $command->getEntityName(),
                        $command->getStorageName()
                    );
                }

                $decodedPayload = $field->getSerializer()->decode(
                    $field,
                    json_encode($command->getPayload(), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR)
                );
                $mergedPayload = array_merge($payload, [$field->getPropertyName() => $decodedPayload]);

                if (isset($writeResults[$uniqueId])) {
                    $changeSet = $writeResults[$uniqueId]->getChangeSet();

                    if ($changeSet instanceof ChangeSet && $command->getChangeSet()) {
                        $command->getChangeSet()->merge($changeSet);
                    }
                }

                $writeResults[$uniqueId] = new EntityWriteResult(
                    $this->getCommandPrimaryKey($command, $primaryKeys),
                    $mergedPayload,
                    $command->getEntityName(),
                    EntityWriteResult::OPERATION_UPDATE,
                    $command->getEntityExistence(),
                    $command->getChangeSet()
                );
            }

            $identifiers[$definition->getEntityName()] = array_values($writeResults);
        }

        return $identifiers;
    }

    /**
     * @return array<string, string>|string
     */
    private function getCommandPrimaryKey(WriteCommand $command, FieldCollection $fields): array|string
    {
        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            $field = $fields->first();

            if ($field instanceof StorageAware) {
                return $field->getSerializer()->decode($field, $primaryKey[$field->getStorageName()]);
            }
        }

        foreach ($fields as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }
            $data[$field->getPropertyName()] = $field->getSerializer()->decode($field, $primaryKey[$field->getStorageName()]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCommandPayload(WriteCommand $command): array
    {
        $payload = [];
        if ($command instanceof InsertCommand || $command instanceof UpdateCommand) {
            $payload = $command->getPayload();
        }

        $definition = $this->registry->getByEntityName($command->getEntityName());

        $fields = $definition->getFields();

        $convertedPayload = [];
        foreach ($payload as $key => $value) {
            $field = $fields->getByStorageName($key);

            if (!$field) {
                continue;
            }

            $convertedPayload[$field->getPropertyName()] = $field->getSerializer()->decode($field, $value);
        }

        $primaryKeys = $definition->getPrimaryKeys();

        foreach ($primaryKeys as $primaryKey) {
            if (!$primaryKey instanceof StorageAware) {
                continue;
            }
            if (\array_key_exists($primaryKey->getPropertyName(), $payload)) {
                continue;
            }

            if (!\array_key_exists($primaryKey->getStorageName(), $command->getPrimaryKey())) {
                throw DataAbstractionLayerException::inconsistentPrimaryKey(
                    $command->getEntityName(),
                    $primaryKey->getStorageName()
                );
            }

            $key = $command->getPrimaryKey()[$primaryKey->getStorageName()];

            $convertedPayload[$primaryKey->getPropertyName()] = $primaryKey->getSerializer()->decode($primaryKey, $key);
        }

        return $convertedPayload;
    }

    /**
     * @param array<array<string, string>> $rawData
     *
     * @return list<string>
     */
    private function getPrimaryKeysOfFkField(EntityDefinition $definition, array $rawData, FkField $fkField): array
    {
        $parent = $fkField->getReferenceDefinition();

        $referenceField = $parent->getFields()->getByStorageName($fkField->getReferenceField());
        if (!$referenceField) {
            throw DataAbstractionLayerException::referenceFieldByStorageNameNotFound(
                $parent->getEntityName(),
                $fkField->getReferenceField()
            );
        }

        $primaryKeys = [];
        foreach ($rawData as $row) {
            if (\array_key_exists($fkField->getPropertyName(), $row)) {
                $primaryKeys[] = $row[$fkField->getPropertyName()];

                continue;
            }

            $fk = $this->fetchForeignKey($definition, $row, $fkField);
            if ($fk !== null) {
                $primaryKeys[] = $fk;
            }
        }

        return $primaryKeys;
    }

    /**
     * @param array<string, string> $rawData
     */
    private function fetchForeignKey(EntityDefinition $definition, array $rawData, FkField $fkField): ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(' . EntityDefinitionQueryHelper::escape($fkField->getStorageName()) . '))'
        );
        $query->from(EntityDefinitionQueryHelper::escape($definition->getEntityName()));

        foreach ($definition->getPrimaryKeys() as $index => $primaryKey) {
            $property = $primaryKey->getPropertyName();

            if ($primaryKey instanceof VersionField || $primaryKey instanceof ReferenceVersionField) {
                continue;
            }

            if (!isset($rawData[$property])) {
                throw DataAbstractionLayerException::inconsistentPrimaryKey(
                    $definition->getEntityName(),
                    $property
                );
            }
            if (!$primaryKey instanceof StorageAware) {
                continue;
            }
            $key = 'primaryKey' . $index;

            $query->andWhere(
                EntityDefinitionQueryHelper::escape($primaryKey->getStorageName()) . ' = :' . $key
            );

            $query->setParameter($key, Uuid::fromHexToBytes($rawData[$property]));
        }

        $fk = $query->executeQuery()->fetchOne();

        return $fk === false ? null : $fk;
    }
}
