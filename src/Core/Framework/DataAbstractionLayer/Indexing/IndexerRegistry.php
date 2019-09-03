<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IndexerRegistry implements EventSubscriberInterface, IndexerRegistryInterface
{
    /**
     * 0    => Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamIndexer
     * 0    => Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer
     * 200  => Shopware\Core\Framework\Search\DataAbstractionLayer\Indexing\SearchKeywordIndexer
     * 300  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductListingPriceIndexer
     * 400  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductPropertyIndexer
     * 500  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer
     * 500  => Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderConfigIndexer
     * 900  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductOptionIndexer
     * 1000 => Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\ChildCountIndexer
     * 1000 => Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\TreeIndexer
     * 1500 => Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\InheritanceIndexer
     *
     * @var IndexerInterface[]
     */
    private $indexer;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Internal working state to prevent endless loop if an indexer fires the EntityWrittenContainerEvent
     *
     * @var bool
     */
    private $working = false;

    public function __construct(iterable $indexer, EventDispatcherInterface $eventDispatcher)
    {
        $this->indexer = $indexer;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['refresh', 500],
            ],
        ];
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        if ($this->working) {
            return;
        }

        $preEvent = new IndexerRegistryStartEvent(new \DateTimeImmutable());
        $this->eventDispatcher->dispatch($preEvent);

        $this->working = true;
        foreach ($this->indexer as $indexer) {
            $indexer->index($timestamp);
        }
        $this->working = false;

        $preEvent = new IndexerRegistryEndEvent(new \DateTimeImmutable());
        $this->eventDispatcher->dispatch($preEvent);
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if ($this->working) {
            return;
        }

        $preEvent = new IndexerRegistryStartEvent(new \DateTimeImmutable(), $event->getContext());
        $this->eventDispatcher->dispatch($preEvent);

        $this->working = true;
        foreach ($this->indexer as $indexer) {
            $indexer->refresh($event);
        }
        $this->working = false;

        $preEvent = new IndexerRegistryEndEvent(new \DateTimeImmutable(), $event->getContext());
        $this->eventDispatcher->dispatch($preEvent);
    }

    public function partial(?string $lastIndexer, $lastId, \DateTimeInterface $timestamp): IndexerRegistryPartialResult
    {
        $preEvent = new IndexerRegistryStartEvent(new \DateTimeImmutable());
        $this->eventDispatcher->dispatch($preEvent);

        foreach ($this->indexer as $index => $indexer) {
            if (!$lastIndexer) {
                return $this->doPartial($indexer, $lastId, $index, $timestamp);
            }

            if ($lastIndexer === get_class($indexer)) {
                return $this->doPartial($indexer, $lastId, $index, $timestamp);
            }
        }

        return new IndexerRegistryPartialResult(null, null);
    }

    private function doPartial(IndexerInterface $indexer, $lastId, $index, \DateTimeInterface $timestamp): IndexerRegistryPartialResult
    {
        $nextId = $indexer->partial($lastId, $timestamp);

        $next = get_class($indexer);

        if ($nextId === null) {
            $next = isset($this->indexer[$index]) ? get_class($this->indexer[$index]) : null;
        }

        return new IndexerRegistryPartialResult($next, $nextId);
    }
}
