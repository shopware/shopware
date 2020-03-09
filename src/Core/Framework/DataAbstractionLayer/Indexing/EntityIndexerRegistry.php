<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EntityIndexerRegistry extends AbstractMessageHandler implements EventSubscriberInterface
{
    public const USE_INDEXING_QUEUE = 'use-indexing-queue';

    /**
     * @var EntityIndexerInterface[]
     */
    private $indexer;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var bool
     */
    private $working = false;

    public function __construct(iterable $indexer, MessageBusInterface $messageBus)
    {
        $this->indexer = $indexer;
        $this->messageBus = $messageBus;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['refresh', 1000],
            ],
        ];
    }

    public function index(bool $useQueue): void
    {
        foreach ($this->indexer as $indexer) {
            $offset = null;

            while ($message = $indexer->iterate($offset)) {
                $message->setIndexer($indexer->getName());

                $this->sendOrHandle($message, $useQueue);

                $offset = $message->getOffset();
            }
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        if ($this->working) {
            return;
        }

        $this->working = true;

        $useQueue = $event->getContext()->hasExtension(self::USE_INDEXING_QUEUE);

        foreach ($this->indexer as $indexer) {
            $message = $indexer->update($event);

            if (!$message) {
                continue;
            }

            $message->setIndexer($indexer->getName());

            $this->sendOrHandle($message, $useQueue);
        }

        $this->working = false;
    }

    public static function getHandledMessages(): iterable
    {
        return [
            EntityIndexingMessage::class,
        ];
    }

    public function handle($message): void
    {
        if (!$message instanceof EntityIndexingMessage) {
            return;
        }

        $indexer = $this->getIndexer($message->getIndexer());

        if (!$indexer) {
            return;
        }

        $indexer->handle($message);
    }

    private function sendOrHandle(EntityIndexingMessage $message, bool $useQueue): void
    {
        if ($useQueue) {
            $this->messageBus->dispatch($message);

            return;
        }

        $this->handle($message);
    }

    private function getIndexer(string $name): ?EntityIndexerInterface
    {
        foreach ($this->indexer as $indexer) {
            if ($indexer->getName() === $name) {
                return $indexer;
            }
        }

        return null;
    }
}
