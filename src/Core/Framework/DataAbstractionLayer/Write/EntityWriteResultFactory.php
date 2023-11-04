<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
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

    public function build(WriteCommandQueue $queue): array
    {
        return $this->buildQueueResults($queue);
    }

    public function resolveDelete(EntityDefinition $definition, array $ids): array
    {
        // resolves mapping relations, inheritance and sub domain entities
        return $this->resolveParents($definition, $ids, true);
    }

    public function resolveWrite(EntityDefinition $definition, array $rawData): array
    {
        // resolve domain parent (order_delivery > order | product_price > product),
        // relations for mapping entities (product_category)
        // and product inheritance (product.parent_id)
        return $this->resolveParents($definition, $rawData);
    }

    public function resolveMappings(array $results): array
    {
        $mappings = [];

        /** @var EntityWriteResult[] $result */
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

            /** @var FkField $field */
            foreach ($fkFields as $field) {
                $reference = $field->getReferenceDefinition()->getEntityName();

                $mappings[$reference] = array_merge($mappings[$reference] ?? [], array_column($ids, $field->getPropertyName()));
            }
        }

        return $mappings;
    }

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

    public function addDeleteResults(array $identifiers, array $notFound, array $parents): WriteResult
    {
        $results = $this->splitResultsByOperation($identifiers);

        $deleted = $this->addParentResults($results['deleted'], $parents);

        $mapped = [];
        $updates = [];
        foreach ($deleted as $entity => $nested) {
            /** @var EntityWriteResult $result */
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
            throw new \RuntimeException(sprintf('Can not detect foreign key for parent definition %s', $parent->getEntityName()));
        }

        $primaryKeys = $this->getPrimaryKeysOfFkField($definition, $ids, $fkField);

        $mapped = array_map(fn ($id) => ['id' => $id], $primaryKeys);

        // recursion call for nested sub entities (order_delivery_position > order_delivery > order)
        $nested = $this->resolveParents($parent, $mapped);

        $entity = $parent->getEntityName();

        $nested[$entity] = array_merge($nested[$entity] ?? [], $primaryKeys, $parentIds[$entity] ?? []);

        return $nested;
    }

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

    private function resolveMappingParents(EntityDefinition $definition, array $rawData): array
    {
        $fkFields = $definition->getFields()->filter(fn (Field $field) => $field instanceof FkField && !$field instanceof ReferenceVersionField);

        $mapping = [];

        /** @var FkField $fkField */
        foreach ($fkFields as $fkField) {
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

    private function fetchParentIds(EntityDefinition $definition, array $rawData): array
    {
        $fetchQuery = sprintf(
            'SELECT DISTINCT LOWER(HEX(parent_id)) as id FROM %s WHERE id IN (:ids)',
            EntityDefinitionQueryHelper::escape($definition->getEntityName())
        );

        $parentIds = $this->connection->fetchAllAssociative(
            $fetchQuery,
            ['ids' => Uuid::fromHexToBytesList(array_column($rawData, 'id'))],
            ['ids' => ArrayParameterType::STRING]
        );

        $ids = array_unique(array_filter(array_column($parentIds, 'id')));

        if (\count($ids) === 0) {
            return [];
        }

        return [$definition->getEntityName() => $ids];
    }

    /**
     * @param EntityWriteResult[][] $results
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

    private function buildQueueResults(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        $order = [];
        // we have to create the written events in the written order, otherwise the version manager would
        // trace the change sets in a wrong order
        foreach ($queue->getCommandsInOrder() as $command) {
            $class = $command->getDefinition()->getEntityName();
            if (isset($order[$class])) {
                continue;
            }
            $order[$class] = $command->getDefinition();
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
                    $payload,
                    $command->getDefinition()->getEntityName(),
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

                $field = $command->getDefinition()->getFields()->getByStorageName($command->getStorageName());

                if (!$field instanceof Field) {
                    throw new \RuntimeException(sprintf('Field by storage name %s not found', $command->getStorageName()));
                }

                $decodedPayload = $field->getSerializer()->decode(
                    $field,
                    json_encode($command->getPayload(), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR)
                );
                $mergedPayload = array_merge($payload, [$field->getPropertyName() => $decodedPayload]);

                $changeSet = [];
                if ($command instanceof ChangeSetAware) {
                    $changeSet = $command->getChangeSet();
                }

                $writeResults[$uniqueId] = new EntityWriteResult(
                    $this->getCommandPrimaryKey($command, $primaryKeys),
                    $mergedPayload,
                    $command->getDefinition()->getEntityName(),
                    EntityWriteResult::OPERATION_UPDATE,
                    $command->getEntityExistence(),
                    $changeSet
                );
            }

            $identifiers[$definition->getEntityName()] = array_values($writeResults);
        }

        return $identifiers;
    }

    private function getCommandPrimaryKey(WriteCommand $command, FieldCollection $fields): array|string
    {
        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            /** @var StorageAware&Field $field */
            $field = $fields->first();

            return $field->getSerializer()->decode($field, $primaryKey[$field->getStorageName()]);
        }

        /** @var StorageAware&Field $field */
        foreach ($fields as $field) {
            $data[$field->getPropertyName()] = $field->getSerializer()->decode($field, $primaryKey[$field->getStorageName()]);
        }

        return $data;
    }

    private function getCommandPayload(WriteCommand $command): array
    {
        $payload = [];
        if ($command instanceof InsertCommand || $command instanceof UpdateCommand) {
            $payload = $command->getPayload();
        }

        $fields = $command->getDefinition()->getFields();

        $convertedPayload = [];
        foreach ($payload as $key => $value) {
            $field = $fields->getByStorageName($key);

            if (!$field) {
                continue;
            }

            $convertedPayload[$field->getPropertyName()] = $field->getSerializer()->decode($field, $value);
        }

        $primaryKeys = $command->getDefinition()->getPrimaryKeys();

        /** @var Field&StorageAware $primaryKey */
        foreach ($primaryKeys as $primaryKey) {
            if (\array_key_exists($primaryKey->getPropertyName(), $payload)) {
                continue;
            }

            if (!\array_key_exists($primaryKey->getStorageName(), $command->getPrimaryKey())) {
                throw new \RuntimeException(
                    sprintf(
                        'Primary key field %s::%s not found in payload or command primary key',
                        $command->getDefinition()->getEntityName(),
                        $primaryKey->getStorageName()
                    )
                );
            }

            $key = $command->getPrimaryKey()[$primaryKey->getStorageName()];

            $convertedPayload[$primaryKey->getPropertyName()] = $primaryKey->getSerializer()->decode($primaryKey, $key);
        }

        return $convertedPayload;
    }

    private function getPrimaryKeysOfFkField(EntityDefinition $definition, array $rawData, FkField $fkField): array
    {
        $parent = $fkField->getReferenceDefinition();

        $referenceField = $parent->getFields()->getByStorageName($fkField->getReferenceField());
        if (!$referenceField) {
            throw new \RuntimeException(
                sprintf(
                    'Can not detect reference field with storage name %s in definition %s',
                    $fkField->getReferenceField(),
                    $parent->getEntityName()
                )
            );
        }

        $primaryKeys = [];
        foreach ($rawData as $row) {
            if (\array_key_exists($fkField->getPropertyName(), $row)) {
                $fk = $row[$fkField->getPropertyName()];
            } else {
                $fk = $this->fetchForeignKey($definition, $row, $fkField);
            }

            $primaryKeys[] = $fk;
        }

        return $primaryKeys;
    }

    private function fetchForeignKey(EntityDefinition $definition, array $rawData, FkField $fkField): string
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

            /* @var Field|StorageAware $primaryKey */
            if (!isset($rawData[$property])) {
                $required = $definition->getPrimaryKeys()->filter(fn (Field $field) => !$field instanceof ReferenceVersionField && !$field instanceof VersionField);

                throw new IncompletePrimaryKeyException($required->getKeys());
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

        if (!$fk) {
            throw new \RuntimeException('Fk can not be detected');
        }

        return (string) $fk;
    }
}
