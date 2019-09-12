<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

class IndexerHandler extends AbstractMessageHandler
{
    /**
     * @var IndexerInterface[]
     */
    private $indexer;

    /**
     * @var IndexerRegistryInterface
     */
    private $registry;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(iterable $indexer, IndexerRegistryInterface $registry, MessageBusInterface $bus)
    {
        $this->indexer = $indexer;
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
        $indexer = $this->getIndexerByName($message->getIndexer());

        if ($message->getActionType() === IndexerMessage::ACTION_INDEX) {
            $indexer->index($message->getTimestamp());
        }

        if ($message->getActionType() === IndexerMessage::ACTION_REFRESH) {
            $indexer->refresh($message->getEntityWrittenContainerEvent());
        }

        if ($message->getActionType() === IndexerMessage::ACTION_PARTIAL) {
            $result = $this->registry->partial($message->getIndexer(), $message->getOffset(), $message->getTimestamp());

            if ($result === null) {
                return;
            }

            // add new message for next id or next indexer
            $new = new IndexerMessage();
            $new->setIndexer($result->getIndexer());
            $new->setOffset($result->getOffset());
            $new->setTimestamp($message->getTimestamp());
            $new->setActionType(IndexerMessage::ACTION_PARTIAL);
            $this->bus->dispatch($new);
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [IndexerMessage::class];
    }

    /**
     * @throws \Exception
     */
    private function getIndexerByName(string $classname): IndexerInterface
    {
        foreach ($this->indexer as $indexer) {
            if (get_class($indexer) === $classname) {
                return $indexer;
            }
        }

        throw new \Exception('Indexer not found: ' . $classname);
    }
}
