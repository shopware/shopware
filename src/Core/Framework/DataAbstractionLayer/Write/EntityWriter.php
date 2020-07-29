<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\SetNullOnDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;

/**
 * Handles all write operations in the system.
 * Builds first a command queue over the WriteCommandExtractor and let execute this queue
 * over the EntityWriteGateway (sql implementation in default).
 */
class EntityWriter implements EntityWriterInterface
{
    /**
     * @var EntityForeignKeyResolver
     */
    private $foreignKeyResolver;

    /**
     * @var WriteCommandExtractor
     */
    private $commandExtractor;

    /**
     * @var EntityWriteGatewayInterface
     */
    private $gateway;

    /**
     * @var LanguageLoaderInterface
     */
    private $languageLoader;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(
        WriteCommandExtractor $writeResource,
        EntityForeignKeyResolver $foreignKeyResolver,
        EntityWriteGatewayInterface $gateway,
        LanguageLoaderInterface $languageLoader,
        DefinitionInstanceRegistry $registry
    ) {
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->commandExtractor = $writeResource;
        $this->gateway = $gateway;
        $this->languageLoader = $languageLoader;
        $this->registry = $registry;
    }

    public function sync(array $operations, WriteContext $context): array
    {
        $commandQueue = new WriteCommandQueue();

        $context->setLanguages(
            $this->languageLoader->loadLanguages()
        );

        foreach ($operations as $operation) {
            if (!$operation instanceof SyncOperation) {
                continue;
            }

            $definition = $this->registry->getByEntityName($operation->getEntity());

            $this->validateWriteInput($operation->getPayload());

            if ($operation->getAction() === SyncOperation::ACTION_DELETE) {
                $this->extractDeleteCommands($definition, $operation->getPayload(), $context, $commandQueue);

                continue;
            }

            $parameters = new WriteParameterBag($definition, $context, '', $commandQueue);
            foreach ($operation->getPayload() as $index => $row) {
                $parameters->setPath('/' . $index);
                $context->resetPaths();

                if ($operation->getAction() === SyncOperation::ACTION_UPSERT) {
                    $this->commandExtractor->extract($row, $parameters);
                }
            }
        }

        $context->getExceptions()->tryToThrow();

        $this->gateway->execute($commandQueue->getCommandsInOrder(), $context);

        return $this->getWriteResults($commandQueue);
    }

    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext);
    }

    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, InsertCommand::class);
    }

    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, UpdateCommand::class);
    }

    /**
     * @throws IncompletePrimaryKeyException
     * @throws RestrictDeleteViolationException
     */
    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): DeleteResult
    {
        $this->validateWriteInput($ids);

        $commandQueue = new WriteCommandQueue();

        $skipped = $this->extractDeleteCommands($definition, $ids, $writeContext, $commandQueue);

        $writeContext->setLanguages($this->languageLoader->loadLanguages());
        $this->gateway->execute($commandQueue->getCommandsInOrder(), $writeContext);

        $identifiers = $this->getWriteResults($commandQueue);

        $results = $this->splitResultsByOperation($identifiers);

        return new DeleteResult($results['deleted'], $skipped, $results['updated']);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     */
    private function getWriteResults(WriteCommandQueue $queue): array
    {
        $identifiers = [];

        $order = [];
        // we have to create the written events in the written order, otherwise the version manager would
        // trace the change sets in a wrong order
        foreach ($queue->getCommandsInOrder() as $command) {
            $class = $command->getDefinition()->getClass();
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
                ->filter(static function (Field $field) {
                    return !$field instanceof VersionField && !$field instanceof ReferenceVersionField;
                });

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
                $decodedPayload = $field->getSerializer()->decode(
                    $field,
                    json_encode($command->getPayload(), JSON_PRESERVE_ZERO_FRACTION)
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

    private function write(EntityDefinition $definition, array $rawData, WriteContext $writeContext, ?string $ensure = null): array
    {
        $this->validateWriteInput($rawData);

        if (!$rawData) {
            return [];
        }

        $commandQueue = new WriteCommandQueue();
        $parameters = new WriteParameterBag($definition, $writeContext, '', $commandQueue);

        $writeContext->setLanguages($this->languageLoader->loadLanguages());

        foreach ($rawData as $index => $row) {
            $parameters->setPath('/' . $index);
            $writeContext->resetPaths();
            $this->commandExtractor->extract($row, $parameters);
        }

        if ($ensure) {
            $commandQueue->ensureIs($definition, $ensure);
        }

        $writeContext->getExceptions()->tryToThrow();

        $this->gateway->execute($commandQueue->getCommandsInOrder(), $writeContext);

        return $this->getWriteResults($commandQueue);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validateWriteInput(array $data): void
    {
        $valid = array_keys($data) === range(0, \count($data) - 1) || $data === [];

        if (!$valid) {
            throw new \InvalidArgumentException('Expected input to be non associative array.');
        }
    }

    /**
     * @return array|string
     */
    private function getCommandPrimaryKey(WriteCommand $command, FieldCollection $fields)
    {
        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            /** @var StorageAware|Field $field */
            $field = $fields->first();

            return Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        foreach ($fields as $field) {
            $data[$field->getPropertyName()] = Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        return $data;
    }

    /**
     * @throws \RuntimeException
     */
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

        /** @var Field|StorageAware $primaryKey */
        foreach ($primaryKeys as $primaryKey) {
            if (\array_key_exists($primaryKey->getPropertyName(), $payload)) {
                continue;
            }

            if (!\array_key_exists($primaryKey->getStorageName(), $command->getPrimaryKey())) {
                throw new \RuntimeException(
                    sprintf(
                        'Primary key field %s::%s not found in payload or command primary key',
                        $command->getDefinition()->getClass(),
                        $primaryKey->getStorageName()
                    )
                );
            }

            $key = $command->getPrimaryKey()[$primaryKey->getStorageName()];

            $convertedPayload[$primaryKey->getPropertyName()] = Uuid::fromBytesToHex($key);
        }

        return $convertedPayload;
    }

    private function addDeleteCascadeCommands(WriteCommandQueue $queue, EntityDefinition $definition, WriteContext $writeContext, array $resolved): void
    {
        if ($definition instanceof MappingEntityDefinition) {
            return;
        }
        $cascades = [];

        $cascadeDeletes = $this->foreignKeyResolver->getAffectedDeletes($definition, $resolved, $writeContext->getContext());

        $cascadeDeletes = array_column($cascadeDeletes, 'restrictions');
        foreach ($cascadeDeletes as $cascadeDelete) {
            $cascades = array_merge_recursive($cascades, $cascadeDelete);
        }

        foreach ($cascades as $affectedDefinitionClass => $keys) {
            $affectedDefinition = $this->registry->getByEntityName($affectedDefinitionClass);

            foreach ($keys as $key) {
                if (!is_array($key)) {
                    $key = ['id' => $key];
                }

                $primary = EntityHydrator::encodePrimaryKey($affectedDefinition, $key, $writeContext->getContext());

                $existence = new EntityExistence($affectedDefinition->getEntityName(), $primary, true, false, false, []);

                $queue->add($affectedDefinition, new CascadeDeleteCommand($affectedDefinition, $primary, $existence));
            }
        }
    }

    private function addSetNullOnDeletesCommands(WriteCommandQueue $queue, EntityDefinition $definition, WriteContext $writeContext, array $resolved): void
    {
        if ($definition instanceof MappingEntityDefinition) {
            return;
        }

        $setNulls = [];
        $setNullsPerPk = $this->foreignKeyResolver->getAffectedSetNulls($definition, $resolved, $writeContext->getContext());

        $setNullsPerPk = array_column($setNullsPerPk, 'restrictions');
        foreach ($setNullsPerPk as $setNull) {
            $setNulls = array_merge_recursive($setNulls, $setNull);
        }

        foreach ($setNulls as $affectedDefinitionClass => $restrictions) {
            $affectedDefinition = $this->registry->getByEntityName($affectedDefinitionClass);

            foreach ($restrictions as $key => $fkFields) {
                $primaryKey = ['id' => $key];
                $payload = ['id' => Uuid::fromHexToBytes($key)];

                $primary = EntityHydrator::encodePrimaryKey($affectedDefinition, $primaryKey, $writeContext->getContext());
                $existence = new EntityExistence($affectedDefinition->getEntityName(), $primary, true, false, false, []);

                foreach ($fkFields as $fkField) {
                    $payload[$fkField] = null;

                    if ($definition->isVersionAware()) {
                        $versionField = str_replace('_id', '_version_id', $fkField);
                        $payload[$versionField] = null;
                    }
                }

                $queue->add($affectedDefinition, new SetNullOnDeleteCommand($affectedDefinition, $payload, $primary, $existence, ''));
            }
        }
    }

    private function resolvePrimaryKeys(array $ids, EntityDefinition $definition, WriteContext $writeContext): array
    {
        $fields = $definition->getPrimaryKeys();

        $resolved = [];
        foreach ($ids as $raw) {
            $mapped = [];

            /** @var Field $field */
            foreach ($fields as $field) {
                if (!($field instanceof StorageAware)) {
                    continue;
                }

                if (\array_key_exists($field->getPropertyName(), $raw)) {
                    $mapped[$field->getStorageName()] = $raw[$field->getPropertyName()];

                    continue;
                }

                if ($field instanceof ReferenceVersionField) {
                    $mapped[$field->getStorageName()] = $writeContext->getContext()->getVersionId();

                    continue;
                }

                if ($field instanceof VersionField) {
                    $mapped[$field->getStorageName()] = $writeContext->getContext()->getVersionId();

                    continue;
                }

                $fieldKeys = $fields
                    ->filter(
                        function (Field $field) {
                            return !$field instanceof VersionField && !$field instanceof ReferenceVersionField;
                        }
                    )
                    ->map(
                        function (Field $field) {
                            return $field->getPropertyName();
                        }
                    );

                throw new IncompletePrimaryKeyException($fieldKeys);
            }

            $resolved[] = $mapped;
        }

        return $resolved;
    }

    private function splitResultsByOperation(array $identifiers): array
    {
        $deleted = [];
        $updated = [];
        foreach ($identifiers as $entityName => $writeResults) {
            $deletedEntities = array_filter($writeResults, function (EntityWriteResult $result): bool {
                return $result->getOperation() === EntityWriteResult::OPERATION_DELETE;
            });
            if (!empty($deletedEntities)) {
                $deleted[$entityName] = $deletedEntities;
            }

            $updatedEntities = array_filter($writeResults, function (EntityWriteResult $result): bool {
                return $result->getOperation() === EntityWriteResult::OPERATION_UPDATE;
            });

            if (!empty($updatedEntities)) {
                $updated[$entityName] = $updatedEntities;
            }
        }

        return ['deleted' => $deleted, 'updated' => $updated];
    }

    private function extractDeleteCommands(EntityDefinition $definition, array $ids, WriteContext $writeContext, WriteCommandQueue $commandQueue): array
    {
        $resolved = $this->resolvePrimaryKeys($ids, $definition, $writeContext);

        if (!$definition instanceof MappingEntityDefinition) {
            $restrictions = $this->foreignKeyResolver->getAffectedDeleteRestrictions($definition, $resolved, $writeContext->getContext());

            if (!empty($restrictions)) {
                $restrictions = array_map(function ($restriction) {
                    return new RestrictDeleteViolation($restriction['pk'], $restriction['restrictions']);
                }, $restrictions);

                throw new RestrictDeleteViolationException($definition, $restrictions);
            }
        }

        $skipped = [];
        foreach ($resolved as $primaryKey) {
            $mappedBytes = array_map(function ($id) {
                return Uuid::fromHexToBytes($id);
            }, $primaryKey);

            $existence = $this->gateway->getExistence($definition, $mappedBytes, [], $commandQueue);

            if (!$existence->exists()) {
                $skipped[$definition->getEntityName()][] = new EntityWriteResult(
                    $primaryKey,
                    $primaryKey,
                    $definition->getEntityName(),
                    EntityWriteResult::OPERATION_DELETE,
                    $existence
                );

                continue;
            }

            $commandQueue->add($definition, new DeleteCommand($definition, $mappedBytes, $existence));
        }

        // we had some logic in the command layer (pre-validate, post-validate, indexer which listens to this events)
        // to trigger this logic for cascade deletes or set nulls, we add a fake commands for the affected rows
        $this->addDeleteCascadeCommands($commandQueue, $definition, $writeContext, $resolved);

        $this->addSetNullOnDeletesCommands($commandQueue, $definition, $writeContext, $resolved);

        return $skipped;
    }
}
