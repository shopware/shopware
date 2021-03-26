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
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\SetNullOnDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;

/**
 * Handles all write operations in the system.
 * Builds first a command queue over the WriteCommandExtractor and let execute this queue
 * over the EntityWriteGateway (sql implementation in default).
 */
class EntityWriter implements EntityWriterInterface
{
    private EntityForeignKeyResolver $foreignKeyResolver;

    private WriteCommandExtractor $commandExtractor;

    private EntityWriteGatewayInterface $gateway;

    private LanguageLoaderInterface $languageLoader;

    private DefinitionInstanceRegistry $registry;

    private EntityWriteResultFactory $writeResultFactory;

    public function __construct(
        WriteCommandExtractor $writeResource,
        EntityForeignKeyResolver $foreignKeyResolver,
        EntityWriteGatewayInterface $gateway,
        LanguageLoaderInterface $languageLoader,
        DefinitionInstanceRegistry $registry,
        EntityWriteResultFactory $writeResultFactory
    ) {
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->commandExtractor = $writeResource;
        $this->gateway = $gateway;
        $this->languageLoader = $languageLoader;
        $this->registry = $registry;
        $this->writeResultFactory = $writeResultFactory;
    }

    // TODO: prefetch
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
            $rawData = $this->commandExtractor->normalize($definition, $operation->getPayload(), $parameters);
            $this->gateway->prefetchExistences($parameters);

            foreach ($rawData as $index => $row) {
                $parameters->setPath('/' . $index);
                $context->resetPaths();

                if ($operation->getAction() === SyncOperation::ACTION_UPSERT) {
                    $this->commandExtractor->extract($row, $parameters);
                }
            }
        }

        $context->getExceptions()->tryToThrow();

        $this->gateway->execute($commandQueue->getCommandsInOrder(), $context);

        return $this->writeResultFactory->build($commandQueue);
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

        $identifiers = $this->writeResultFactory->build($commandQueue);

        $results = $this->splitResultsByOperation($identifiers);

        return new DeleteResult($results['deleted'], $skipped, $results['updated']);
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

        $rawData = $this->commandExtractor->normalize($definition, $rawData, $parameters);
        $writeContext->getExceptions()->tryToThrow();

        $this->gateway->prefetchExistences($parameters);

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

        return $this->writeResultFactory->build($commandQueue);
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

    private function addDeleteCascadeCommands(WriteCommandQueue $queue, EntityDefinition $definition, WriteContext $writeContext, array $resolved): void
    {
        if ($definition instanceof MappingEntityDefinition) {
            return;
        }
        $cascades = $this->foreignKeyResolver->getAffectedDeletes($definition, $resolved, $writeContext->getContext());

        foreach ($cascades as $affectedDefinitionClass => $keys) {
            $affectedDefinition = $this->registry->getByEntityName($affectedDefinitionClass);

            foreach ($keys as $key) {
                if (!\is_array($key)) {
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

        $setNulls = $this->foreignKeyResolver->getAffectedSetNulls($definition, $resolved, $writeContext->getContext());

        foreach ($setNulls as $affectedDefinitionClass => $restrictions) {
            [$entity, $field] = explode('.', $affectedDefinitionClass);

            $affectedDefinition = $this->registry->getByEntityName($entity);

            foreach ($restrictions as $key) {
                $payload = ['id' => Uuid::fromHexToBytes($key), $field => null];

                $primary = EntityHydrator::encodePrimaryKey($affectedDefinition, ['id' => $key], $writeContext->getContext());

                $existence = new EntityExistence($affectedDefinition->getEntityName(), $primary, true, false, false, []);

                if ($definition->isVersionAware()) {
                    $versionField = str_replace('_id', '_version_id', $field);
                    $payload[$versionField] = null;
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

            foreach ($fields as $field) {
                $property = $field->getPropertyName();
                if (!($field instanceof StorageAware)) {
                    continue;
                }

                if (\array_key_exists($property, $raw)) {
                    $mapped[$field->getStorageName()] = $raw[$property];

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
        $parameters = new WriteParameterBag($definition, $writeContext, '', $commandQueue);
        $ids = $this->commandExtractor->normalize($definition, $ids, $parameters);
        $this->gateway->prefetchExistences($parameters);

        $resolved = $this->resolvePrimaryKeys($ids, $definition, $writeContext);

        if (!$definition instanceof MappingEntityDefinition) {
            $restrictions = $this->foreignKeyResolver->getAffectedDeleteRestrictions($definition, $resolved, $writeContext->getContext(), true);

            if (!empty($restrictions)) {
                throw new RestrictDeleteViolationException($definition, [new RestrictDeleteViolation($restrictions)]);
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
