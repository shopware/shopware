<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Api\Exception\IncompletePrimaryKeyException;
use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
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

    // TODO: prefetch
    /**
     * @param SyncOperation[] $operations
     *
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

            $this->validateWriteInput($operation->getPayload());

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

        $this->gateway->execute($commandQueue->getCommandsInOrder(), $context);

        $result = $this->factory->build($commandQueue);

        $notFound = array_merge_recursive(...$notFound);

        $writes = array_merge_recursive(...$writes);

        $deletes = array_merge_recursive(...$deletes);

        $result = $this->factory->addParentResults($result, $writes);

        $result = $this->factory->addDeleteResults($result, $notFound, $deletes);

        return $result;
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<mixed>
     */
    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext);
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<mixed>
     */
    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, InsertCommand::class);
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<mixed>
     */
    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        return $this->write($definition, $rawData, $writeContext, UpdateCommand::class);
    }

    /**
     * @param array<mixed> $ids
     *
     * @throws IncompletePrimaryKeyException
     * @throws RestrictDeleteViolationException
     */
    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): WriteResult
    {
        $this->validateWriteInput($ids);

        $parents = [];
        if (!$writeContext->hasState('merge-scope')) {
            $parents = $this->factory->resolveDelete($definition, $ids);
        }

        $commandQueue = new WriteCommandQueue();
        $notFound = $this->extractDeleteCommands($definition, $ids, $writeContext, $commandQueue);

        $writeContext->setLanguages($this->languageLoader->loadLanguages());
        $this->gateway->execute($commandQueue->getCommandsInOrder(), $writeContext);

        $result = $this->factory->build($commandQueue);

        $parents = array_merge_recursive($parents, $this->factory->resolveMappings($result));

        return $this->factory->addDeleteResults($result, $notFound, $parents);
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<mixed>
     */
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

        $ordered = $commandQueue->getCommandsInOrder();

        $this->gateway->execute($ordered, $writeContext);

        $result = $this->factory->build($commandQueue);

        $parents = array_merge(
            $this->factory->resolveWrite($definition, $rawData),
            $this->factory->resolveMappings($result)
        );

        return $this->factory->addParentResults($result, $parents);
    }

    /**
     * @param array<mixed> $data
     *
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
     * @param array<mixed> $resolved
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

                $queue->add($affectedDefinition, new UpdateCommand($affectedDefinition, [], $primary, $existence, ''));
            }
        }
    }

    /**
     * @param array<mixed> $resolved
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

                $queue->add($affectedDefinition, new CascadeDeleteCommand($affectedDefinition, $primary, $existence));
            }
        }
    }

    /**
     * @param array<mixed> $resolved
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

            /** @var SetNullOnDelete $flag */
            $flag = $associationField->getFlag(SetNullOnDelete::class);

            foreach ($restrictions as $key) {
                $payload = ['id' => Uuid::fromHexToBytes($key), $field => null];

                $primary = EntityHydrator::encodePrimaryKey($affectedDefinition, ['id' => $key], $writeContext->getContext());

                $existence = new EntityExistence($affectedDefinition->getEntityName(), $primary, true, false, false, []);

                if ($definition->isVersionAware()) {
                    $versionField = str_replace('_id', '_version_id', $field);
                    $payload[$versionField] = null;
                }

                $queue->add($affectedDefinition, new SetNullOnDeleteCommand($affectedDefinition, $payload, $primary, $existence, '', $flag->isEnforcedByConstraint()));
            }
        }
    }

    /**
     * @param array<mixed> $ids
     *
     * @return array<mixed>
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

                $fieldKeys = $fields
                    ->filter(
                        fn (Field $field) => !$field instanceof VersionField && !$field instanceof ReferenceVersionField
                    )
                    ->map(
                        fn (Field $field) => $field->getPropertyName()
                    );

                throw new IncompletePrimaryKeyException($fieldKeys);
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
            /**
             * @var string $key
             * @var string $value
             */
            foreach ($primaryKey as $key => $value) {
                /**
                 * Primary key fields are always storage aware.
                 *
                 * @var Field&StorageAware $field
                 */
                $field = $definition->getFields()->get($key);

                $mappedBytes[$field->getStorageName()] = $field->getSerializer()->encode(
                    $field,
                    EntityExistence::createForEntity($definition->getEntityName(), [$key => $value]),
                    new KeyValuePair($key, $value, true),
                    $parameters,
                )->current();
            }

            $existence = $this->gateway->getExistence($definition, $mappedBytes, [], $commandQueue);

            if ($existence->exists()) {
                $commandQueue->add($definition, new DeleteCommand($definition, $mappedBytes, $existence));

                continue;
            }

            $stripped = [];
            /**
             * @var string $key
             * @var string $value
             */
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
