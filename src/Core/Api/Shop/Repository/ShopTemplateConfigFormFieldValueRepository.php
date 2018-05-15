<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Repository;

use Shopware\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\AggregatorResult;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueDetailCollection;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormFieldValueDefinition;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueAggregationResultLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueBasicLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueDetailLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueIdSearchResultLoadedEvent;
use Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue\ShopTemplateConfigFormFieldValueSearchResultLoadedEvent;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldValueSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Version\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShopTemplateConfigFormFieldValueRepository implements RepositoryInterface
{
    /**
     * @var EntityReaderInterface
     */
    private $reader;

    /**
     * @var EntitySearcherInterface
     */
    private $searcher;

    /**
     * @var EntityAggregatorInterface
     */
    private $aggregator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var VersionManager
     */
    private $versionManager;

    public function __construct(
       EntityReaderInterface $reader,
       VersionManager $versionManager,
       EntitySearcherInterface $searcher,
       EntityAggregatorInterface $aggregator,
       EventDispatcherInterface $eventDispatcher
   ) {
        $this->reader = $reader;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
        $this->versionManager = $versionManager;
    }

    public function search(Criteria $criteria, ApplicationContext $context): ShopTemplateConfigFormFieldValueSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = ShopTemplateConfigFormFieldValueSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new ShopTemplateConfigFormFieldValueSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ApplicationContext $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(ShopTemplateConfigFormFieldValueDefinition::class, $criteria, $context);

        $event = new ShopTemplateConfigFormFieldValueAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ApplicationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(ShopTemplateConfigFormFieldValueDefinition::class, $criteria, $context);

        $event = new ShopTemplateConfigFormFieldValueIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ApplicationContext $context): ShopTemplateConfigFormFieldValueBasicCollection
    {
        /** @var ShopTemplateConfigFormFieldValueBasicCollection $entities */
        $entities = $this->reader->readBasic(ShopTemplateConfigFormFieldValueDefinition::class, $ids, $context);

        $event = new ShopTemplateConfigFormFieldValueBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ApplicationContext $context): ShopTemplateConfigFormFieldValueDetailCollection
    {
        /** @var ShopTemplateConfigFormFieldValueDetailCollection $entities */
        $entities = $this->reader->readDetail(ShopTemplateConfigFormFieldValueDefinition::class, $ids, $context);

        $event = new ShopTemplateConfigFormFieldValueDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(ShopTemplateConfigFormFieldValueDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(ShopTemplateConfigFormFieldValueDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(ShopTemplateConfigFormFieldValueDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(ShopTemplateConfigFormFieldValueDefinition::class, $ids, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ApplicationContext $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(ShopTemplateConfigFormFieldValueDefinition::class, $id, WriteContext::createFromApplicationContext($context), $name, $versionId);
    }

    public function merge(string $versionId, ApplicationContext $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromApplicationContext($context));
    }
}
