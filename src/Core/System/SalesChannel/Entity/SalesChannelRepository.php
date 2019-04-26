<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\RepositorySearchDetector;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SalesChannelRepository
{
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

    /**
     * @var string|EntityDefinition|SalesChannelDefinitionInterface
     */
    protected $definition;

    public function __construct(
        string $definition,
        EntityReaderInterface $reader,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
        $this->definition = $definition;
    }

    public function search(Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $instance = new $this->definition();
        if ($instance instanceof SalesChannelDefinitionInterface) {
            $this->definition::processCriteria($criteria, $context);
        }

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context)->getAggregations();
        }

        if (!RepositorySearchDetector::isSearchRequired($this->definition, $criteria)) {
            $entities = $this->read($criteria, $context);

            return new EntitySearchResult($entities->count(), $entities, $aggregations, $criteria, $context->getContext());
        }

        $ids = $this->searchIds($criteria, $context);

        $readCriteria = new Criteria($ids->getIds());
        foreach ($criteria->getAssociations() as $key => $associationCriteria) {
            $readCriteria->addAssociation($key, $associationCriteria);
        }

        $entities = $this->read($readCriteria, $context);

        $search = $ids->getData();

        /** @var Entity $element */
        foreach ($entities as $element) {
            if (!array_key_exists($element->getUniqueIdentifier(), $search)) {
                continue;
            }

            $data = $search[$element->getUniqueIdentifier()];
            unset($data['primary_key']);

            if (empty($data)) {
                continue;
            }

            $element->addExtension('search', new ArrayEntity($data));
        }

        $result = new EntitySearchResult($ids->getTotal(), $entities, $aggregations, $criteria, $context->getContext());

        $event = new EntitySearchResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        $event = new SalesChannelEntitySearchResultLoadedEvent($this->definition, $result, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, SalesChannelContext $context): AggregatorResult
    {
        $instance = new $this->definition();
        if ($instance instanceof SalesChannelDefinitionInterface) {
            $this->definition::processCriteria($criteria, $context);
        }

        $result = $this->aggregator->aggregate($this->definition, $criteria, $context->getContext());

        $event = new EntityAggregationResultLoadedEvent($this->definition, $result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, SalesChannelContext $context): IdSearchResult
    {
        $instance = new $this->definition();
        if ($instance instanceof SalesChannelDefinitionInterface) {
            $this->definition::processCriteria($criteria, $context);
        }

        $result = $this->searcher->search($this->definition, $criteria, $context->getContext());

        $event = new SalesChannelEntityIdSearchResultLoadedEvent($this->definition, $result, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    private function read(Criteria $criteria, SalesChannelContext $context): EntityCollection
    {
        /** @var EntityCollection $entities */
        $entities = $this->reader->read($this->definition, $criteria, $context->getContext());

        $event = new EntityLoadedEvent($this->definition, $entities->getElements(), $context->getContext());
        $this->eventDispatcher->dispatch($event->getName(), $event);

        $event = new SalesChannelEntityLoadedEvent($this->definition, $entities->getElements(), $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }
}
