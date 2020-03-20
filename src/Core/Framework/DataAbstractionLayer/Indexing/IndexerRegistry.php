<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry instead
 */
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

    public function partial(?string $lastIndexer, ?array $lastId, \DateTimeInterface $timestamp): ?IndexerRegistryPartialResult
    {
        $indexers = $this->getIndexers();

        foreach ($indexers as $index => $indexer) {
            if (!$lastIndexer) {
                return $this->doPartial($indexer, $lastId, $index, $timestamp);
            }

            if ($lastIndexer === $indexer::getName()) {
                return $this->doPartial($indexer, $lastId, $index, $timestamp);
            }
        }

        return null;
    }

    private function doPartial(IndexerInterface $indexer, ?array $lastId, $index, \DateTimeInterface $timestamp): ?IndexerRegistryPartialResult
    {
        $nextId = $indexer->partial($lastId, $timestamp);

        $next = $indexer::getName();

        if ($nextId !== null) {
            return new IndexerRegistryPartialResult($next, $nextId);
        }
        ++$index;
        $indexers = $this->getIndexers();

        if (!isset($indexers[$index])) {
            return null;
        }

        return new IndexerRegistryPartialResult($indexers[$index]::getName(), null);
    }

    private function getIndexers()
    {
        if (!is_array($this->indexer)) {
            return array_values(iterator_to_array($this->indexer));
        }

        return array_values($this->indexer);
    }
}
