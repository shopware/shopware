<?php declare(strict_types=1);

namespace Shopware\Content\Category\Repository;

use Shopware\Content\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Content\Category\Collection\CategoryTranslationDetailCollection;
use Shopware\Content\Category\Definition\CategoryTranslationDefinition;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationAggregationResultLoadedEvent;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationBasicLoadedEvent;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationDetailLoadedEvent;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationIdSearchResultLoadedEvent;
use Shopware\Content\Category\Event\CategoryTranslation\CategoryTranslationSearchResultLoadedEvent;
use Shopware\Content\Category\Struct\CategoryTranslationSearchResult;
use Shopware\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\AggregatorResult;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\Version\Service\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CategoryTranslationRepository implements RepositoryInterface
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
     * @var \Shopware\Framework\ORM\Version\Service\VersionManager
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

    public function search(Criteria $criteria, ApplicationContext $context): CategoryTranslationSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = CategoryTranslationSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new CategoryTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, ApplicationContext $context): AggregatorResult
    {
        $result = $this->aggregator->aggregate(CategoryTranslationDefinition::class, $criteria, $context);

        $event = new CategoryTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, ApplicationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(CategoryTranslationDefinition::class, $criteria, $context);

        $event = new CategoryTranslationIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, ApplicationContext $context): CategoryTranslationBasicCollection
    {
        /** @var CategoryTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(CategoryTranslationDefinition::class, $ids, $context);

        $event = new CategoryTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, ApplicationContext $context): CategoryTranslationDetailCollection
    {
        /** @var CategoryTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(CategoryTranslationDefinition::class, $ids, $context);

        $event = new CategoryTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->update(CategoryTranslationDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->upsert(CategoryTranslationDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->insert(CategoryTranslationDefinition::class, $data, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, ApplicationContext $context): GenericWrittenEvent
    {
        $affected = $this->versionManager->delete(CategoryTranslationDefinition::class, $ids, WriteContext::createFromApplicationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function createVersion(string $id, ApplicationContext $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->versionManager->createVersion(CategoryTranslationDefinition::class, $id, WriteContext::createFromApplicationContext($context), $name, $versionId);
    }

    public function merge(string $versionId, ApplicationContext $context): void
    {
        $this->versionManager->merge($versionId, WriteContext::createFromApplicationContext($context));
    }
}
