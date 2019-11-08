<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Converter\ConverterService;
use Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

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
     * @var ConverterService
     */
    private $converterService;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        Connection $connection,
        ConverterService $converterService
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->connection = $connection;
        $this->converterService = $converterService;
    }

    /**
     * @param SyncOperation[] $operations
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult
    {
        $this->connection->beginTransaction();

        $hasError = false;
        $results = [];
        foreach ($operations as $operation) {
            $result = $this->execute($operation, $context, $behavior);

            $results[$operation->getKey()] = $result;

            $hasError = $result->hasError();

            if ($hasError && $behavior->failOnError()) {
                break;
            }
        }

        if ($behavior->failOnError() && $hasError) {
            $this->connection->rollBack();
        } else {
            $this->connection->commit();
        }

        return new SyncResult($results, $hasError === false);
    }

    private function execute(SyncOperation $operation, Context $context, SyncBehavior $behavior): SyncOperationResult
    {
        $repository = $this->definitionRegistry->getRepository($operation->getEntity());

        $payload = array_values($operation->getPayload());

        $results = [];

        $success = true;

        foreach ($payload as $key => $record) {
            $result = $this->writeRecord($operation, $context, $repository, $record, $behavior->getApiVersion());

            $results[$key] = $result;

            $success = ($result['error'] === null);

            if ($success === false && $behavior->failOnError()) {
                break;
            }
        }

        return new SyncOperationResult($operation->getKey(), $results, $success);
    }

    private function writeRecord(SyncOperation $operation, Context $context, EntityRepositoryInterface $repository, $record, int $apiVersion): array
    {
        $error = null;
        $result = null;

        if (!$this->converterService->isAllowed($operation->getEntity(), null, $apiVersion)) {
            return [
                'error' => sprintf('Writing of entity: "%s" is not allowed in v%d of the api.', $operation->getEntity(), $apiVersion),
                'entities' => [],
            ];
        }

        $exception = new ApiConversionException();
        $record = $this->converterService->convertPayload($repository->getDefinition(), $record, $apiVersion, $exception);

        try {
            $exception->tryToThrow();
        } catch (ApiConversionException $e) {
            return ['error' => iterator_to_array($e->getErrors()), 'entities' => []];
        }

        try {
            switch (mb_strtolower($operation->getAction())) {
                case SyncOperation::ACTION_DELETE:
                    $result = $repository->delete([$record], $context);
                    break;

                case SyncOperation::ACTION_UPSERT:
                    $result = $repository->upsert([$record], $context);
                    break;

                default:
                    throw new \RuntimeException(
                        sprintf('provided action %s is not supported. Following actions are supported: delete, upsert', $operation->getAction())
                    );
            }
        } catch (\Throwable $e) {
            $error = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
        }

        $entities = $this->getWrittenEntities($result);

        return ['error' => $error, 'entities' => $entities];
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
