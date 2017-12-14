<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Repository;

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
use Shopware\Api\Payment\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Api\Payment\Collection\PaymentMethodTranslationDetailCollection;
use Shopware\Api\Payment\Definition\PaymentMethodTranslationDefinition;
use Shopware\Api\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationAggregationResultLoadedEvent;
use Shopware\Api\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationBasicLoadedEvent;
use Shopware\Api\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationDetailLoadedEvent;
use Shopware\Api\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationSearchResultLoadedEvent;
use Shopware\Api\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationUuidSearchResultLoadedEvent;
use Shopware\Api\Payment\Struct\PaymentMethodTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PaymentMethodTranslationRepository implements RepositoryInterface
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

    public function search(Criteria $criteria, TranslationContext $context): PaymentMethodTranslationSearchResult
    {
        $uuids = $this->searchUuids($criteria, $context);

        $entities = $this->readBasic($uuids->getUuids(), $context);

        $aggregations = null;
        if ($criteria->getAggregations()) {
            $aggregations = $this->aggregate($criteria, $context);
        }

        $result = PaymentMethodTranslationSearchResult::createFromResults($uuids, $entities, $aggregations);

        $event = new PaymentMethodTranslationSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->aggregator->aggregate(PaymentMethodTranslationDefinition::class, $criteria, $context);

        $event = new PaymentMethodTranslationAggregationResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        $result = $this->searcher->search(PaymentMethodTranslationDefinition::class, $criteria, $context);

        $event = new PaymentMethodTranslationUuidSearchResultLoadedEvent($result);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    public function readBasic(array $uuids, TranslationContext $context): PaymentMethodTranslationBasicCollection
    {
        /** @var PaymentMethodTranslationBasicCollection $entities */
        $entities = $this->reader->readBasic(PaymentMethodTranslationDefinition::class, $uuids, $context);

        $event = new PaymentMethodTranslationBasicLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function readDetail(array $uuids, TranslationContext $context): PaymentMethodTranslationDetailCollection
    {
        /** @var PaymentMethodTranslationDetailCollection $entities */
        $entities = $this->reader->readDetail(PaymentMethodTranslationDefinition::class, $uuids, $context);

        $event = new PaymentMethodTranslationDetailLoadedEvent($entities, $context);
        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $entities;
    }

    public function update(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->update(PaymentMethodTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->upsert(PaymentMethodTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }

    public function create(array $data, TranslationContext $context): GenericWrittenEvent
    {
        $affected = $this->writer->insert(PaymentMethodTranslationDefinition::class, $data, WriteContext::createFromTranslationContext($context));
        $event = GenericWrittenEvent::createFromWriterResult($affected, $context, []);
        $this->eventDispatcher->dispatch(GenericWrittenEvent::NAME, $event);

        return $event;
    }
}
