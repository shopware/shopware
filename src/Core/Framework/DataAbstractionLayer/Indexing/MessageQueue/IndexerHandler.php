<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

class IndexerHandler extends AbstractMessageHandler
{
    /**
     * @var IndexerRegistryInterface
     */
    private $registry;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(IndexerRegistryInterface $registry, MessageBusInterface $bus)
    {
        $this->registry = $registry;
        $this->bus = $bus;
    }

    /**
     * @param IndexerMessage $message
     *
     * @throws \Exception
     */
    public function handle($message): void
    {
        $result = $this->registry->partial($message->getCurrentIndexerName(), $message->getOffset(), $message->getTimestamp());
        if ($result === null) {
            return;
        }

        $remainingIndexers = $message->getIndexerNames();

        // current indexer is finished
        if ($result->getOffset() === null) {
            array_shift($remainingIndexers);
        }

        if (empty($remainingIndexers)) {
            // no indexers left
            return;
        }

        // add new message for next id or next indexer
        $new = new IndexerMessage($remainingIndexers);
        $new->setOffset($result->getOffset());
        $new->setTimestamp($message->getTimestamp());
        $this->bus->dispatch($new);
    }

    public static function getHandledMessages(): iterable
    {
        return [IndexerMessage::class];
    }
}
