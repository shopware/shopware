<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
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
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;

/**
 * @internal
 *
 * Handles all write operations in the system.
 * Builds first a command queue over the WriteCommandExtractor and let execute this queue
 * over the EntityWriteGateway (sql implementation in default).
 */
#[Package('core')]
class EntityWriter implements EntityWriterInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly WriteCommandExtractor $commandExtractor,
        private readonly EntityForeignKeyResolver $foreignKeyResolver,
        private readonly EntityWriteGatewayInterface $gateway,
        private readonly LanguageLoaderInterface $languageLoader,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly EntityWriteResultFactory $factory
    ) {
    }

    /**
     * @throw InvalidSyncOperationException
     */
    public function sync(array $operations, WriteContext $context): WriteResult
    {
        $commandQueue = new WriteCommandQueue();

        $context->setLanguages(
            $this->languageLoader->loadLanguages()
        );

        $writes = [];
        $notFound = [];
        $deletes = [];

        foreach ($operations as $operation) {
            if (!$operation instanceof SyncOperation) {
                continue;
            }

            $this->validateSyncOperationInput($operation);

            $definition = $this->registry->getByEntityName($operation->getEntity());

            WriteInputValidator::validate($operation->getPayload());

            if ($operation->getAction() === SyncOperation::ACTION_DELETE) {
                $deletes[] = $this->factory->resolveDelete($definition, $operation->getPayload());

                $notFound[] = $this->extractDeleteCommands($definition, $operation->getPayload(), $context, $commandQueue);

                continue;
            }

            if ($operation->getAction() === SyncOperation::ACTION_UPSERT) {
                $parameters = new WriteParameterBag($definition, $context, '', $commandQueue);

                $payload = $this->commandExtractor->normalize($definition, $operation->getPayload(), $parameters);
                $this->gateway->prefetchExistences($parameters);

                $key = $operation->getKey();

                foreach ($payload as $index => $row) {
                    $parameters->setPath('/' . $key . '/' . $index);
                    $context->resetPaths();
                    $this->commandExtractor->extract($row, $parameters);
                }

                $writes[] = $this->factory->resolveWrite($definition, $payload);
            }
        }

        $context->getExceptions()->tryToThrow();

        $this->gateway->execute($commandQueue->getCommandsInOrder($this->registry), $context);

        $result = $this->factory->build($commandQueue);

        $notFound = array_merge_recursive(...$notFound);

        $writes = array_merge_recursive(...$writes);

        $deletes = array_merge_recursive(...$deletes);

        $result = $this->factory->addParentResults($result, $writes);

        $result = $this->factory->addDeleteResults($result, $notFound, $deletes);

        return $result;
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
     * @throws RestrictDeleteViolationException
     */
    public function delete(EntityDefinition $definition, array $rawData, WriteContext $writeContext): WriteResult
    {
        WriteInputValidator::validate($rawData);

        $parents = [];
        if (!$writeContext->hasState('merge-scope')) {
            $parents = $this->factory->resolveDelete($definition, $rawData);
        }

        $commandQueue = new WriteCommandQueue();
        $notFound = $this->extractDeleteCommands($definition, $rawData, $writeContext, $commandQueue);

        $writeContext->setLanguages($this->languageLoader->loadLanguages());
        $this->gateway->execute($commandQueue->getCommandsInOrder($this->registry), $writeContext);

        $result = $this->factory->build($commandQueue);

        $parents = array_merge_recursive($parents, $this->factory->resolveMappings($result));

        return $this->factory->addDeleteResults($result, $notFound, $parents);
    }

    /**
     * @param array<array<string, mixed>> $rawData
     *
     * @return array<string, array<EntityWriteResult>>
     */
    private function write(EntityDefinition $definition, array $rawData, WriteContext $writeContext, ?string $ensure = null): array
    {
        WriteInputValidator::validate($rawData);

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

        $ordered = $commandQueue->getCommandsInOrder($this->registry);

        $this->gateway->execute($ordered, $writeContext);

        $result = $this->factory->build($commandQueue);

        $parents = array_merge(
            $this->factory->resolveWrite($definition, $rawData),
            $this->factory->resolveMappings($result)
        );

        return $this->factory->addParentResults($result, $parents);
    }

    /**
     * @throws InvalidSyncOperationException
     */
    private function validateSyncOperationInput(SyncOperation $operation): void
    {
        $errors = $operation->validate();
        if (\count($errors)) {
            throw new InvalidSyncOperationException(sprintf('Invalid sync operation. %s', implode(' ', $errors)));
        }
    }

    /**
     * @param array<array<string, string>> $resolved
     */
    private function addReverseInheritedCommands(WriteCommandQueue $queue, EntityDefinition $definition, WriteContext $writeContext, array $resolved): void
    {
        if ($definition instanceof MappingEntityDefinition) {
            return;
        }
        $cascades = $this->foreignKeyResolver->getAllReverseInherited($definition, $resolved, $writeContext->getContext());

        foreach ($cascades as $affectedDefinitionClass => $keys) {
            $affectedDefinition = $this->registry->getByEntityName($affectedDefinitionClass);

            foreach ($keys as $key) {
                if (!\is_array($key)) {
                    $key = ['id' => $key];
                }

                $primary = EntityHydrator::encodePrimaryKey($affectedDefinition, $key, $writeContext->getContext());

                $existence = new EntityExistence($affectedDefinition->getEntityName(), $primary, true, false, false, []);

                $command = new UpdateCommand($affectedDefinition, [], $primary, $existence, '');

                $queue->add(
                    $affectedDefinition->getEntityName(),
                    WriteCommandQueue::hashedPrimary($this->registry, $command),
                    $command
                );
            }
        }
    }

    /**
     * @param array<array<string, string>> $resolved
     */
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

                $command = new CascadeDeleteCommand($affectedDefinition, $primary, $existence);

                $identifier = WriteCommandQueue::hashedPrimary(
                    $this->registry,
                    $command
                );

                $queue->add(
                    $affectedDefinition->getEntityName(),
                    $identifier,
                    $command
                );
            }
        }
    }

    /**
     * @param array<array<string, string>> $resolved
     */
    private function addSetNullOnDeletesCommands(WriteCommandQueue $queue, EntityDefinition $definition, WriteContext $writeContext, array $resolved): void
    {
        if ($definition instanceof MappingEntityDefinition) {
            return;
        }

        $setNullFields = $definition->getFields()->filterByFlag(SetNullOnDelete::class);

        $setNulls = $this->foreignKeyResolver->getAffectedSetNulls($definition, $resolved, $writeContext->getContext());

        foreach ($setNulls as $affectedDefinitionClass => $restrictions) {
            [$entity, $field] = explode('.', $affectedDefinitionClass);

            $affectedDefinition = $this->registry->getByEntityName($entity);

            /** @var AssociationField $associationField */
            $associationField = $setNullFields
                ->filter(fn (Field $setNullField) => $setNullField instanceof AssociationField && $setNullField->getReferenceField() === $field)
                ->first();

            $flag = $associationField->getFlag(SetNullOnDelete::class);
            $isEnforced = $flag !== null ? $flag->isEnforcedByConstraint() : true;

            foreach ($restrictions as $key) {
                $payload = ['id' => Uuid::fromHexToBytes($key), $field => null];

                $primary = EntityHydrator::encodePrimaryKey($affectedDefinition, ['id' => $key], $writeContext->getContext());

                $existence = new EntityExistence($affectedDefinition->getEntityName(), $primary, true, false, false, []);

                if ($definition->isVersionAware()) {
                    $versionField = str_replace('_id', '_version_id', $field);
                    $payload[$versionField] = null;
                }

                $command = new SetNullOnDeleteCommand($affectedDefinition, $payload, $primary, $existence, '', $isEnforced);

                $identifier = WriteCommandQueue::hashedPrimary($this->registry, $command);

                $queue->add(
                    $affectedDefinition->getEntityName(),
                    $identifier,
                    $command
                );
            }
        }
    }

    /**
     * @param array<mixed> $ids
     *
     * @return list<array<string, string>>
     */
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
                    $mapped[$property] = $raw[$property];

                    continue;
                }

                if ($field instanceof ReferenceVersionField) {
                    $mapped[$property] = $writeContext->getContext()->getVersionId();

                    continue;
                }

                if ($field instanceof VersionField) {
                    $mapped[$property] = $writeContext->getContext()->getVersionId();

                    continue;
                }

                throw DataAbstractionLayerException::inconsistentPrimaryKey($definition->getEntityName(), $property);
            }

            $resolved[] = $mapped;
        }

        return $resolved;
    }

    /**
     * @param array<mixed> $ids
     *
     * @return array<mixed>
     */
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
            /** @var array<string, string> $mappedBytes */
            $mappedBytes = [];
            foreach ($primaryKey as $key => $value) {
                $field = $definition->getFields()->get($key);
                if (!$field instanceof StorageAware) {
                    continue;
                }

                $mappedBytes[$field->getStorageName()] = $field->getSerializer()->encode(
                    $field,
                    EntityExistence::createForEntity($definition->getEntityName(), [$key => $value]),
                    new KeyValuePair($key, $value, true),
                    $parameters,
                )->current();
            }

            $existence = $this->gateway->getExistence($definition, $mappedBytes, [], $commandQueue);

            if ($existence->exists()) {
                $command = new DeleteCommand($definition, $mappedBytes, $existence);

                $identifier = WriteCommandQueue::hashedPrimary($this->registry, $command);

                $commandQueue->add(
                    $definition->getEntityName(),
                    $identifier,
                    $command
                );

                continue;
            }

            $stripped = [];
            foreach ($primaryKey as $key => $value) {
                $field = $definition->getFields()->get($key);

                if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                    continue;
                }
                $stripped[$key] = $value;
            }

            $skipped[$definition->getEntityName()][] = new EntityWriteResult(
                \count($stripped) === 1 ? array_shift($stripped) : $stripped,
                $stripped,
                $definition->getEntityName(),
                EntityWriteResult::OPERATION_DELETE,
                $existence
            );
        }

        // we had some logic in the command layer (pre-validate, post-validate, indexer which listens to this events)
        // to trigger this logic for cascade deletes or set nulls, we add a fake commands for the affected rows
        $this->addReverseInheritedCommands($commandQueue, $definition, $writeContext, $resolved);

        $this->addDeleteCascadeCommands($commandQueue, $definition, $writeContext, $resolved);

        $this->addSetNullOnDeletesCommands($commandQueue, $definition, $writeContext, $resolved);

        return $skipped;
    }
}
