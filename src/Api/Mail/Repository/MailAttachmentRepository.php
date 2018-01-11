<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Repository;

use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntityAggregatorInterface;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Mail\Collection\MailAttachmentBasicCollection;
use Shopware\Api\Mail\Collection\MailAttachmentDetailCollection;
use Shopware\Api\Mail\Definition\MailAttachmentDefinition;
use Shopware\Api\Mail\Event\MailAttachment\MailAttachmentAggregationResultLoadedEvent;
use Shopware\Api\Mail\Event\MailAttachment\MailAttachmentBasicLoadedEvent;
use Shopware\Api\Mail\Event\MailAttachment\MailAttachmentDetailLoadedEvent;
use Shopware\Api\Mail\Event\MailAttachment\MailAttachmentIdSearchResultLoadedEvent;
use Shopware\Api\Mail\Event\MailAttachment\MailAttachmentSearchResultLoadedEvent;
use Shopware\Api\Mail\Struct\MailAttachmentSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MailAttachmentRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): MailAttachmentSearchResult
    {
        $ids = $this->searchIds($criteria, $context);

        $entities = $this->readBasic($ids->getIds(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = MailAttachmentSearchResult::createFromResults($ids, $entities, $aggregations);

        $event = new MailAttachmentSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(MailAttachmentDefinition::class, $criteria, $context);

        $event = new MailAttachmentAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchIds(Criteria $criteria, TranslationContext $context): IdSearchResult
    {
        $result = $this->searcher->search(MailAttachmentDefinition::class, $criteria, $context);

        $event = new MailAttachmentIdSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $ids, TranslationContext $context): MailAttachmentBasicCollection
    {
        /** @var MailAttachmentBasicCollection $entities */
        $entities = $this->reader->readBasic(MailAttachmentDefinition::class, $ids, $context);

        $event = new MailAttachmentBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $ids, TranslationContext $context): MailAttachmentDetailCollection
    {
        /** @var MailAttachmentDetailCollection $entities */
        $entities = $this->reader->readDetail(MailAttachmentDefinition::class, $ids, $context);

        $event = new MailAttachmentDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(MailAttachmentDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(MailAttachmentDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(MailAttachmentDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithWrittenEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function delete(array $ids, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->delete(MailAttachmentDefinition::class, $ids, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createWithDeletedEvents($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
