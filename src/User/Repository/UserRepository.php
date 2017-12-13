<?php declare(strict_types=1);

namespace Shopware\User\Repository;

use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\UuidSearchResult;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Context\Struct\TranslationContext;
use Shopware\User\Collection\UserBasicCollection;
use Shopware\User\Collection\UserDetailCollection;
use Shopware\User\Definition\UserDefinition;
use Shopware\User\Event\User\UserAggregationResultLoadedEvent;
use Shopware\User\Event\User\UserBasicLoadedEvent;
use Shopware\User\Event\User\UserDetailLoadedEvent;
use Shopware\User\Event\User\UserSearchResultLoadedEvent;
use Shopware\User\Event\User\UserUuidSearchResultLoadedEvent;
use Shopware\User\Struct\UserSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UserRepository implements RepositoryInterface
{
    /**
     * @var EntityReaderInterface
     */
    private $reader;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

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

    public function __construct(
        EntityReaderInterface $reader,
        EntityWriterInterface $writer,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->searcher = $searcher;
        $this->aggregator = $aggregator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function search(Criteria $criteria, TranslationContext $context): UserSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = UserSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new UserSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(UserDefinition::class, $criteria, $context);

        $event = new UserAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(UserDefinition::class, $criteria, $context);

        $event = new UserUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): UserBasicCollection
    {
        /** @var UserBasicCollection $entities */
        $entities = $this->reader->readBasic(UserDefinition::class, $uuids, $context);

        $event = new UserBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): UserDetailCollection
    {
        /** @var UserDetailCollection $entities */
        $entities = $this->reader->readDetail(UserDefinition::class, $uuids, $context);

        $event = new UserDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(UserDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(UserDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(UserDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
