<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

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
     * @var iterable
     */
    private $indexers;

    public function __construct(MessageBusInterface $bus, iterable $indexer)
    {
        $this->bus = $bus;
        $this->indexers = $indexer;
    }

    public function partial(\DateTimeInterface $timestamp, ?array $indexers = null): void
    {
        $scheduledIndexers = [];
        foreach ($this->indexers as $indexer) {
            $indexerName = $indexer::getName();
            if ($indexers !== null && !in_array($indexerName, $indexers, true)) {
                continue;
            }
            $scheduledIndexers[] = $indexerName;
        }

        if (empty($scheduledIndexers)) {
            return;
        }

        $message = new IndexerMessage($scheduledIndexers);
        $message->setTimestamp($timestamp);
        $this->bus->dispatch($message);
    }
}
