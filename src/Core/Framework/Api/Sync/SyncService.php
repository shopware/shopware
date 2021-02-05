<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SyncService implements SyncServiceInterface
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

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
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult
    {
        if ($behavior->getIndexingBehavior() !== null) {
            $context->addExtension($behavior->getIndexingBehavior(), new ArrayEntity());
        }

        // allows to execute all writes inside a single transaction and a single entity write event
        if ($behavior->useSingleOperation()) {
            $result = $this->writer->sync($operations, WriteContext::createFromContext($context));

            $event = EntityWrittenContainerEvent::createWithWrittenEvents($result, $context, []);

            $this->eventDispatcher->dispatch($event);

            $ids = $this->getWrittenEntities($event);

            return new SyncResult($ids, !empty($ids));
        }

        if ($behavior->failOnError()) {
            $this->connection->beginTransaction();
        }

        $hasError = false;
        $results = [];
        foreach ($operations as $operation) {
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
                    sprintf('provided action %s is not supported. Following actions are supported: delete, upsert', $operation->getAction())
                );
        }
    }

    private function upsertRecords(
        SyncOperation $operation,
        Context $context,
        EntityRepositoryInterface $repository
    ): SyncOperationResult {
        $results = [];

        $records = array_values($operation->getPayload());
        $definition = $repository->getDefinition();

        foreach ($records as $index => $record) {
            try {
                $record = $this->convertToApiVersion($record, $definition, $index);

                $result = $repository->upsert([$record], $context);
                $results[$index] = [
                    'entities' => $this->getWrittenEntities($result),
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
        EntityRepositoryInterface $repository
    ): SyncOperationResult {
        $results = [];

        $records = array_values($operation->getPayload());
        $definition = $repository->getDefinition();

        foreach ($records as $index => $record) {
            try {
                $record = $this->convertToApiVersion($record, $definition, $index);

                $result = $repository->delete([$record], $context);
                $results[$index] = [
                    'entities' => $this->getWrittenEntities($result),
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

    private function convertToApiVersion(array $record, EntityDefinition $definition, int $writeIndex): array
    {
        $exception = new ApiConversionException();

        $converted = $this->apiVersionConverter->convertPayload($definition, $record, $exception, "/${writeIndex}");
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

    private function getWrittenEntities(?EntityWrittenContainerEvent $result): array
    {
        if ($result === null) {
            return [];
        }

        $entities = [];

        $events = $result->getEvents();
        if ($events === null) {
            return [];
        }

        /** @var EntityWrittenEvent $event */
        foreach ($events as $event) {
            $entity = $event->getEntityName();

            if (!isset($entities[$entity])) {
                $entities[$entity] = [];
            }

            $entities[$entity] = array_merge($entities[$entity], $event->getIds());
        }

        ksort($entities);

        return $entities;
    }
}
