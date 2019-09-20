<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

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
     * @var IndexerInterface[]|iterable
     */
    private $indexer;

    public function __construct(MessageBusInterface $bus, iterable $indexer)
    {
        $this->bus = $bus;
        $this->indexer = $indexer;
    }

    public function partial(\DateTimeInterface $timestamp): void
    {
        // it is important that we do not throw a message into the bus for every indexer, otherwise the priority is no longer respected
        $indexer = null;
        foreach ($this->indexer as $loop) {
            $indexer = $loop;
            break;
        }

        if (!$indexer) {
            return;
        }

        $message = new IndexerMessage();
        $message->setIndexer(get_class($indexer));
        $message->setTimestamp($timestamp);
        $message->setActionType(IndexerMessage::ACTION_PARTIAL);

        $this->bus->dispatch($message);
    }
}
