<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Doctrine\DBAL\ConnectionException;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
class SyncService implements SyncServiceInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly EntitySearcherInterface $searcher,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly SyncFkResolver $syncFkResolver
    ) {
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

        $this->loopOperations($operations, $context);

        if (\count($behavior->getSkipIndexers())) {
            $context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_SKIP, new ArrayEntity(['skips' => $behavior->getSkipIndexers()]));
        }

        if (
            $behavior->getIndexingBehavior() !== null
            && \in_array($behavior->getIndexingBehavior(), [EntityIndexerRegistry::DISABLE_INDEXING, EntityIndexerRegistry::USE_INDEXING_QUEUE], true)
        ) {
            $context->addState($behavior->getIndexingBehavior());
        }

        $result = $this->writer->sync($operations, WriteContext::createFromContext($context));

        $writes = EntityWrittenContainerEvent::createWithWrittenEvents($result->getWritten(), $context, []);
        $deletes = EntityWrittenContainerEvent::createWithDeletedEvents($result->getDeleted(), $context, []);

        if ($deletes->getEvents() !== null) {
            $writes->addEvent(...$deletes->getEvents()->getElements());
        }

        $this->eventDispatcher->dispatch($writes);

        $ids = $this->getWrittenEntities($result->getWritten());

        $deleted = $this->getWrittenEntitiesByEvent($deletes);

        $notFound = $this->getWrittenEntities($result->getNotFound());

        return new SyncResult($ids, $notFound, $deleted);
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
     * Function to loop through all operations and provide some special handling for wildcard operations, or other short hands
     *
     * @param SyncOperation[] $operations
     */
    private function loopOperations(array $operations, Context $context): void
    {
        foreach ($operations as $operation) {
            if ($operation->getAction() === SyncOperation::ACTION_DELETE && $operation->hasCriteria()) {
                $this->handleCriteriaDelete($operation, $context);

                continue;
            }

            if ($operation->getAction() === SyncOperation::ACTION_UPSERT) {
                $resolved = $this->syncFkResolver->resolve($operation->getEntity(), $operation->getPayload());

                $operation->replacePayload($resolved);
            }
        }
    }

    private function handleCriteriaDelete(SyncOperation $operation, Context $context): void
    {
        $definition = $this->registry->getByEntityName($operation->getEntity());

        if (!$definition instanceof MappingEntityDefinition) {
            throw ApiException::invalidSyncCriteriaException($operation->getKey());
        }

        $criteria = $this->criteriaBuilder->fromArray(['filter' => $operation->getCriteria()], new Criteria(), $definition, $context);

        if (empty($criteria->getFilters())) {
            throw ApiException::invalidSyncCriteriaException($operation->getKey());
        }

        $ids = $this->searcher->search($definition, $criteria, $context);

        $operation->replacePayload(\array_values($ids->getIds()));
    }
}
