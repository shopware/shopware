<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\RepositorySearchDetector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\Event\SalesChannelProcessCriteriaEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 */
#[Package('buyers-experience')]
class SalesChannelRepository
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly EntityReaderInterface $reader,
        private readonly EntitySearcherInterface $searcher,
        private readonly EntityAggregatorInterface $aggregator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityLoadedEventFactory $eventFactory
    ) {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     *
     * @return EntitySearchResult<TEntityCollection>
     */
    public function search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        if (!$criteria->getTitle()) {
            return $this->_search($criteria, $salesChannelContext);
        }

        return Profiler::trace($criteria->getTitle(), fn () => $this->_search($criteria, $salesChannelContext), 'saleschannel-repository');
    }

    public function aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection
    {
        if (!$criteria->getTitle()) {
            return $this->_aggregate($criteria, $salesChannelContext);
        }

        return Profiler::trace($criteria->getTitle(), fn () => $this->_aggregate($criteria, $salesChannelContext), 'saleschannel-repository');
    }

    public function searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        if (!$criteria->getTitle()) {
            return $this->_searchIds($criteria, $salesChannelContext);
        }

        return Profiler::trace($criteria->getTitle(), fn () => $this->_searchIds($criteria, $salesChannelContext), 'saleschannel-repository');
    }

    /**
     * @throws InconsistentCriteriaIdsException
     *
     * @return EntitySearchResult<TEntityCollection>
     */
    private function _search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        $criteria = clone $criteria;

        $this->processCriteria($criteria, $salesChannelContext);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $salesChannelContext);
        }
        if (!RepositorySearchDetector::isSearchRequired($this->definition, $criteria)) {
            $entities = $this->read($criteria, $salesChannelContext);

            return new EntitySearchResult($this->definition->getEntityName(), $entities->count(), $entities, $aggregations, $criteria, $salesChannelContext->getContext());
        }

        $ids = $this->doSearch($criteria, $salesChannelContext);

        if (empty($ids->getIds())) {
            /** @var TEntityCollection $collection */
            $collection = $this->definition->getCollectionClass();

            return new EntitySearchResult($this->definition->getEntityName(), $ids->getTotal(), new $collection(), $aggregations, $criteria, $salesChannelContext->getContext());
        }

        $readCriteria = $criteria->cloneForRead($ids->getIds());

        $entities = $this->read($readCriteria, $salesChannelContext);

        $search = $ids->getData();

        foreach ($entities as $element) {
            if (!\array_key_exists($element->getUniqueIdentifier(), $search)) {
                continue;
            }

            $data = $search[$element->getUniqueIdentifier()];
            unset($data['id']);

            if (empty($data)) {
                continue;
            }

            $element->addExtension('search', new ArrayEntity($data));
        }

        $result = new EntitySearchResult($this->definition->getEntityName(), $ids->getTotal(), $entities, $aggregations, $criteria, $salesChannelContext->getContext());
        $result->addState(...$ids->getStates());

        $event = new EntitySearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event, $event->getName());

        $event = new SalesChannelEntitySearchResultLoadedEvent($this->definition, $result, $salesChannelContext);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    private function _aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection
    {
        $criteria = clone $criteria;

        $this->processCriteria($criteria, $salesChannelContext);

        $result = $this->aggregator->aggregate($this->definition, $criteria, $salesChannelContext->getContext());

        $event = new EntityAggregationResultLoadedEvent($this->definition, $result, $salesChannelContext->getContext());
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    private function _searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        $criteria = clone $criteria;

        $this->processCriteria($criteria, $salesChannelContext);

        return $this->doSearch($criteria, $salesChannelContext);
    }

    /**
     * @return TEntityCollection
     */
    private function read(Criteria $criteria, SalesChannelContext $salesChannelContext): EntityCollection
    {
        $criteria = clone $criteria;

        /** @var TEntityCollection $entities */
        $entities = $this->reader->read($this->definition, $criteria, $salesChannelContext->getContext());

        if ($criteria->getFields() === []) {
            $events = $this->eventFactory->createForSalesChannel($entities->getElements(), $salesChannelContext);
        } else {
            $events = $this->eventFactory->createPartialForSalesChannel($entities->getElements(), $salesChannelContext);
        }

        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $entities;
    }

    private function doSearch(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        $result = $this->searcher->search($this->definition, $criteria, $salesChannelContext->getContext());

        $event = new SalesChannelEntityIdSearchResultLoadedEvent($this->definition, $result, $salesChannelContext);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    private function processCriteria(Criteria $topCriteria, SalesChannelContext $salesChannelContext): void
    {
        if (!$this->definition instanceof SalesChannelDefinitionInterface) {
            return;
        }

        $queue = [
            ['definition' => $this->definition, 'criteria' => $topCriteria, 'path' => ''],
        ];

        $maxCount = 100;

        $processed = [];

        // process all associations breadth-first
        while (!empty($queue) && --$maxCount > 0) {
            $cur = array_shift($queue);

            $definition = $cur['definition'];
            $criteria = $cur['criteria'];
            $path = $cur['path'];
            $processedKey = $path . $definition::class;

            if (isset($processed[$processedKey])) {
                continue;
            }

            if ($definition instanceof SalesChannelDefinitionInterface) {
                $definition->processCriteria($criteria, $salesChannelContext);

                $eventName = \sprintf('sales_channel.%s.process.criteria', $definition->getEntityName());
                $event = new SalesChannelProcessCriteriaEvent($criteria, $salesChannelContext);

                $this->eventDispatcher->dispatch($event, $eventName);
            }

            $processed[$processedKey] = true;

            foreach ($criteria->getAssociations() as $associationName => $associationCriteria) {
                // find definition
                $field = $definition->getField($associationName);
                if (!$field instanceof AssociationField) {
                    continue;
                }

                $referenceDefinition = $field->getReferenceDefinition();
                $queue[] = ['definition' => $referenceDefinition, 'criteria' => $associationCriteria, 'path' => $path . '.' . $associationName];

                if (!$field instanceof ManyToManyAssociationField) {
                    continue;
                }

                $referenceDefinition = $field->getToManyReferenceDefinition();
                $queue[] = ['definition' => $referenceDefinition, 'criteria' => $associationCriteria, 'path' => $path . '.' . $associationName];
            }
        }
    }
}
