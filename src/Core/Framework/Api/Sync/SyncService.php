<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionNotAllowedException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

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

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        Connection $connection,
        ApiVersionConverter $apiVersionConverter
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->connection = $connection;
        $this->apiVersionConverter = $apiVersionConverter;
    }

    /**
     * @param SyncOperation[] $operations
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult
    {
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

            $hasError = $result->hasError();

            if ($hasError) {
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
            case 'upsert':
                return $this->upsertRecords($operation, $context, $repository);

            case 'delete':
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
                $record = $this->convertToApiVersion($record, $definition, $operation->getApiVersion(), $index);

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
                $record = $this->convertToApiVersion($record, $definition, $operation->getApiVersion(), $index);

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

    private function convertToApiVersion(array $record, EntityDefinition $definition, int $apiVersion, int $writeIndex)
    {
        $exception = new ApiConversionException();

        if (!$this->apiVersionConverter->isAllowed($definition->getEntityName(), null, $apiVersion)) {
            $exception->add(new ApiConversionNotAllowedException($definition->getEntityName(), $apiVersion), "/${writeIndex}");
            $exception->tryToThrow();
        }

        $converted = $this->apiVersionConverter->convertPayload($definition, $record, $apiVersion, $exception, "/${writeIndex}");
        $exception->tryToThrow();

        return $converted;
    }

    private function getWriteError(\Throwable $exception, int $writeIndex): WriteException
    {
        if ($exception instanceof WriteException) {
            foreach ($exception->getExceptions() as $innerException) {
                if ($innerException instanceof WriteConstraintViolationException) {
                    $innerException->setPath(preg_replace('/^\/0/', "/{$writeIndex}", $innerException->getPath()));
                }
            }

            return $exception;
        }

        return (new WriteException())->add($exception);
    }

    private function getWrittenEntities(?EntityWrittenContainerEvent $result): array
    {
        if (!$result) {
            return [];
        }

        $entities = [];

        /** @var EntityWrittenEvent $event */
        foreach ($result->getEvents() as $event) {
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
