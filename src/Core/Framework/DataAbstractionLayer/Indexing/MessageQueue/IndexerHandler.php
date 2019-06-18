<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

class IndexerHandler extends AbstractMessageHandler
{
    /**
     * @var IndexerInterface[]
     */
    private $indexer;

    public function __construct(iterable $indexer)
    {
        $this->indexer = $indexer;
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
