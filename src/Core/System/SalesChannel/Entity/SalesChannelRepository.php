<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\RepositorySearchDetector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SalesChannelRepository implements SalesChannelRepositoryInterface
{
    /**
     * @var EntityDefinition|SalesChannelDefinitionInterface
     */
    protected $definition;

    /**
     * @var EntityReaderInterface
     */
    protected $reader;

    /**
     * @var EntitySearcherInterface
     */
    protected $searcher;

    /**
     * @var EntityAggregatorInterface
     */
    protected $aggregator;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(
        EntityDefinition $definition,
        EntityReaderInterface $reader,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->definition = $definition;
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        if ($this->definition instanceof SalesChannelDefinitionInterface) {
            $this->definition->processCriteria($criteria, $salesChannelContext);
        }

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $salesChannelContext);
        }

        if (!RepositorySearchDetector::isSearchRequired($this->definition, $criteria)) {
            $entities = $this->read($criteria, $salesChannelContext);

            return new EntitySearchResult(
                $entities->count(),
                $entities,
                $aggregations,
                $criteria,
                $salesChannelContext->getContext()
            );
        }

        $ids = $this->doSearch($criteria, $salesChannelContext);

        $readCriteria = $criteria->cloneForRead($ids->getIds());

        $entities = $this->read($readCriteria, $salesChannelContext);

        $search = $ids->getData();

        /** @var Entity $element */
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

        $result = new EntitySearchResult(
            $ids->getTotal(),
            $entities,
            $aggregations,
            $criteria,
            $salesChannelContext->getContext()
        );

        $event = new EntitySearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event, $event->getName());

        $event = new SalesChannelEntitySearchResultLoadedEvent($this->definition, $result, $salesChannelContext);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    public function aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection
    {
        if ($this->definition instanceof SalesChannelDefinitionInterface) {
            $this->definition->processCriteria($criteria, $salesChannelContext);
        }

        $result = $this->aggregator->aggregate($this->definition, $criteria, $salesChannelContext->getContext());

        $event = new EntityAggregationResultLoadedEvent($this->definition, $result, $salesChannelContext->getContext());
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }

    public function searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        if ($this->definition instanceof SalesChannelDefinitionInterface) {
            $this->definition->processCriteria($criteria, $salesChannelContext);
        }

        return $this->doSearch($criteria, $salesChannelContext);
    }

    private function read(Criteria $criteria, SalesChannelContext $salesChannelContext): EntityCollection
    {
        /** @var EntityCollection $entities */
        $entities = $this->reader->read($this->definition, $criteria, $salesChannelContext->getContext());

        $event = new EntityLoadedEvent($this->definition, $entities->getElements(), $salesChannelContext->getContext());
        $this->eventDispatcher->dispatch($event, $event->getName());

        $event = new SalesChannelEntityLoadedEvent($this->definition, $entities->getElements(), $salesChannelContext);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $entities;
    }

    private function doSearch(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        $result = $this->searcher->search($this->definition, $criteria, $salesChannelContext->getContext());

        $event = new SalesChannelEntityIdSearchResultLoadedEvent($this->definition, $result, $salesChannelContext);
        $this->eventDispatcher->dispatch($event, $event->getName());

        return $result;
    }
}
