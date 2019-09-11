<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches the tagged indexer like the IndexerRegistry but the work happens inside the message queue.
 */
class IndexerMessageSender
{
    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var IndexerInterface[]
     */
    private $indexer;

    public function __construct(MessageBusInterface $bus, iterable $indexer)
    {
        $this->bus = $bus;
        $this->indexer = $indexer;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        foreach ($this->indexer as $indexer) {
            $message = new IndexerMessage();
            $message->setActionType(IndexerMessage::ACTION_INDEX);
            $message->setTimestamp($timestamp);
            $message->setIndexer(get_class($indexer));
            $this->bus->dispatch($message);
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        foreach ($this->indexer as $indexer) {
            $message = new IndexerMessage();
            $message->setActionType(IndexerMessage::ACTION_REFRESH);
            $message->setEntityWrittenContainerEvent($event);
            $message->setIndexer(get_class($indexer));
            $this->bus->dispatch($message);
        }
    }

    public function partial(\DateTimeInterface $timestamp): void
    {
        foreach ($this->indexer as $indexer) {
            $message = new IndexerMessage();
            $message->setIndexer(get_class($indexer));
            $message->setTimestamp($timestamp);
            $message->setActionType(IndexerMessage::ACTION_PARTIAL);
            $this->bus->dispatch($message);
        }
    }
}
