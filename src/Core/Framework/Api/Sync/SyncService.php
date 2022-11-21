<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package core
 */
class SyncService implements SyncServiceInterface
{
    private DefinitionInstanceRegistry $definitionRegistry;

    private Connection $connection;

    private ApiVersionConverter $apiVersionConverter;

    private EntityWriterInterface $writer;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        Connection $connection,
        ApiVersionConverter $apiVersionConverter,
        EntityWriterInterface $writer,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->connection = $connection;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->writer = $writer;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param SyncOperation[] $operations
     *
     * @throws ConnectionException
     * @throws InvalidSyncOperationException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult
    {
        ReplicaConnection::ensurePrimary();

        $context = clone $context;

        if (\count($behavior->getSkipIndexers())) {
            $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP, new ArrayEntity($behavior->getSkipIndexers()));
        }

        if (
            $behavior->getIndexingBehavior() !== null
            && \in_array($behavior->getIndexingBehavior(), [EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE], true)
        ) {
            // @deprecated tag:v6.5.0 - complete if statement will be removed, context.state should be used instead
            if (!Feature::isActive('v6.5.0.0')) {
                $context->addExtension($behavior->getIndexingBehavior(), new ArrayEntity());
            }

            $context->addState($behavior->getIndexingBehavior());
        }

        // allows to execute all writes inside a single transaction and a single entity write event
        // @internal (flag:FEATURE_NEXT_15815) tag:v6.5.0 - Remove "IF" condition - useSingleOperation is always true
        if ($behavior->useSingleOperation()) {
            $result = $this->writer->sync($operations, WriteContext::createFromContext($context));

            $writes = EntityWrittenContainerEvent::createWithWrittenEvents($result->getWritten(), $context, []);
            $deletes = EntityWrittenContainerEvent::createWithWrittenEvents($result->getDeleted(), $context, []);

            if ($deletes->getEvents() !== null) {
                $writes->addEvent(...$deletes->getEvents()->getElements());
            }

            $this->eventDispatcher->dispatch($writes);

            $ids = $this->getWrittenEntities($result->getWritten());

            $deleted = $this->getWrittenEntitiesByEvent($deletes);

            $notFound = $this->getWrittenEntities($result->getNotFound());

            //@internal (flag:FEATURE_NEXT_15815) - second construct parameter removed - simply remove if condition and all other code below
            if (Feature::isActive('FEATURE_NEXT_15815')) {
                return new SyncResult($ids, $notFound, $deleted);
            }

            return new SyncResult($ids, true, $notFound, $deleted);
        }

        //@deprecated tag:v6.5.0 (flag:FEATURE_NEXT_15815) - remove all code below and all functions which will are no longer used
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'Sync api can only be used in single operation mode in v6.5.0.0'
        );

        if ($behavior->failOnError()) {
            $this->connection->beginTransaction();
        }

        $hasError = false;
        $results = [];
        foreach ($operations as $operation) {
            $this->validateSyncOperationInput($operation);

            if (!$behavior->failOnError()) {
                //begin a new transaction for every operation to provide chunk-safe operations
                $this->connection->beginTransaction();
            }

            $result = $this->execute($operation, $context);

            $results[$operation->getKey()] = $result;

            if ($result->hasError()) {
                $hasError = true;
                if ($behavior->failOnError()) {
                    foreach ($results as $result) {
                        $result->resetEntities();
                    }

                    continue;
                }
                $this->connection->rollBack();
            } elseif (!$behavior->failOnError()) {
                // Only commit if transaction not already marked as rollback
                if (!$this->connection->isRollbackOnly()) {
                    $this->connection->commit();
                } else {
                    $this->connection->rollBack();
                }
            }
        }

        if ($behavior->failOnError()) {
            // Only commit if transaction not already marked as rollback
            if ($hasError === false && !$this->connection->isRollbackOnly()) {
                $this->connection->commit();
            } else {
                $this->connection->rollBack();
            }
        }

        return new SyncResult($results, $hasError === false);
    }

    private function execute(SyncOperation $operation, Context $context): SyncOperationResult
    {
        $repository = $this->definitionRegistry->getRepository($operation->getEntity());

        switch (mb_strtolower($operation->getAction())) {
            case SyncOperation::ACTION_UPSERT:
                return $this->upsertRecords($operation, $context, $repository);

            case SyncOperation::ACTION_DELETE:
                return $this->deleteRecords($operation, $context, $repository);

            default:
                throw new \RuntimeException(
                    sprintf(
                        'provided action "%s" is not supported. Following actions are supported: %s',
                        $operation->getAction(),
                        implode(', ', $operation->getSupportedActions())
                    )
                );
        }
    }

    private function upsertRecords(
        SyncOperation $operation,
        Context $context,
        EntityRepository $repository
    ): SyncOperationResult {
        $results = [];

        $records = array_values($operation->getPayload());
        $definition = $repository->getDefinition();

        foreach ($records as $index => $record) {
            try {
                $record = $this->convertToApiVersion($record, $definition, $index);

                $result = $repository->upsert([$record], $context);
                $results[$index] = [
                    'entities' => $this->getWrittenEntitiesByEvent($result),
                    'errors' => [],
                ];
            } catch (\Throwable $exception) {
                $writeException = $this->getWriteError($exception, $index);
                $errors = [];
                foreach ($writeException->getErrors() as $error) {
                    $errors[] = $error;
                }

                $results[$index] = [
                    'entities' => [],
                    'errors' => $errors,
                ];
            }
        }

        return new SyncOperationResult($results);
    }

    private function deleteRecords(
        SyncOperation $operation,
        Context $context,
        EntityRepository $repository
    ): SyncOperationResult {
        $results = [];

        $records = array_values($operation->getPayload());
        $definition = $repository->getDefinition();

        foreach ($records as $index => $record) {
            try {
                $record = $this->convertToApiVersion($record, $definition, $index);

                $result = $repository->delete([$record], $context);

                $results[$index] = [
                    'entities' => $this->getWrittenEntitiesByEvent($result),
                    'errors' => [],
                ];
            } catch (\Throwable $exception) {
                $writeException = $this->getWriteError($exception, $index);
                $errors = [];
                foreach ($writeException->getErrors() as $error) {
                    $errors[] = $error;
                }

                $results[$index] = [
                    'entities' => [],
                    'errors' => $errors,
                ];
            }
        }

        return new SyncOperationResult($results);
    }

    /**
     * @param array<string, mixed|null> $record
     *
     * @return array<string, mixed|null>
     */
    private function convertToApiVersion(array $record, EntityDefinition $definition, int $writeIndex): array
    {
        $exception = new ApiConversionException();

        $converted = $this->apiVersionConverter->convertPayload($definition, $record, $exception, "/{$writeIndex}");
        $exception->tryToThrow();

        return $converted;
    }

    private function getWriteError(\Throwable $exception, int $writeIndex): WriteException
    {
        if ($exception instanceof WriteException) {
            foreach ($exception->getExceptions() as $innerException) {
                if ($innerException instanceof WriteConstraintViolationException) {
                    $path = preg_replace('/^\/0/', "/{$writeIndex}", $innerException->getPath());
                    if ($path !== null) {
                        $innerException->setPath($path);
                    }
                }
            }

            return $exception;
        }

        return (new WriteException())->add($exception);
    }

    /**
     * @param array<string, EntityWriteResult[]> $grouped
     *
     * @return array<string, array<int, mixed>>
     */
    private function getWrittenEntities(array $grouped): array
    {
        $mapped = [];

        foreach ($grouped as $entity => $results) {
            foreach ($results as $result) {
                $mapped[$entity][] = $result->getPrimaryKey();
            }
        }

        ksort($mapped);

        return $mapped;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function getWrittenEntitiesByEvent(EntityWrittenContainerEvent $result): array
    {
        $entities = [];

        /** @var EntityWrittenEvent $event */
        foreach ($result->getEvents() ?? [] as $event) {
            $entity = $event->getEntityName();

            if (!isset($entities[$entity])) {
                $entities[$entity] = [];
            }

            $entities[$entity] = array_merge($entities[$entity], $event->getIds());
        }

        ksort($entities);

        return $entities;
    }

    /**
     * @deprecated tag:v6.5.0 - Sync Operation will be validated inside EntityWriter instead.
     *
     * @throws InvalidSyncOperationException
     */
    private function validateSyncOperationInput(SyncOperation $operation): void
    {
        $errors = $operation->validate();
        if (\count($errors)) {
            throw new InvalidSyncOperationException(sprintf('Invalid sync operation. %s', implode(' ', $errors)));
        }
    }
}
