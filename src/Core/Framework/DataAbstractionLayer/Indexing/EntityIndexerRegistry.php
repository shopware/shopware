<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EntityIndexerRegistry extends AbstractMessageHandler implements EventSubscriberInterface
{
    public const USE_INDEXING_QUEUE = 'use-queue-indexing';
    public const DISABLE_INDEXING = 'disable-indexing';

    /**
     * @var EntityIndexer[]
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

    public static function getHandledMessages(): iterable
    {
        return [
            EntityIndexingMessage::class,
            IterateEntityIndexerMessage::class,
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

        if ($event->getContext()->hasExtension(self::DISABLE_INDEXING)) {
            $this->working = false;

            return;
        }

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

    public function handle($message): void
    {
        if ($message instanceof EntityIndexingMessage) {
            $indexer = $this->getIndexer($message->getIndexer());

            if ($indexer) {
                $indexer->handle($message);
            }

            return;
        }

        if ($message instanceof IterateEntityIndexerMessage) {
            $next = $this->iterateIndexer($message->getIndexer(), $message->getOffset(), true);

            if (!$next) {
                return;
            }

            $this->messageBus->dispatch(new IterateEntityIndexerMessage($message->getIndexer(), $next->getOffset()));

            return;
        }
    }

    public function sendIndexingMessage(array $indexer = []): void
    {
        if (empty($indexer)) {
            $indexer = [];
            foreach ($this->indexer as $loop) {
                $indexer[] = $loop->getName();
            }
        }

        if (empty($indexer)) {
            return;
        }

        foreach ($indexer as $name) {
            $this->messageBus->dispatch(new IterateEntityIndexerMessage($name, null));
        }
    }

    public function has(string $name): bool
    {
        return $this->getIndexer($name) !== null;
    }

    private function sendOrHandle(EntityIndexingMessage $message, bool $useQueue): void
    {
        if ($useQueue || $message->forceQueue()) {
            $this->messageBus->dispatch($message);

            return;
        }
        $this->handle($message);
    }

    private function getIndexer(string $name): ?EntityIndexer
    {
        foreach ($this->indexer as $indexer) {
            if ($indexer->getName() === $name) {
                return $indexer;
            }
        }

        return null;
    }

    private function iterateIndexer(string $name, $offset, bool $useQueue): ?EntityIndexingMessage
    {
        $indexer = $this->getIndexer($name);

        if (!$indexer instanceof EntityIndexer) {
            throw new \RuntimeException(sprintf('Entity indexer with name %s not found', $name));
        }

        $message = $indexer->iterate($offset);
        if (!$message) {
            return null;
        }

        $message->setIndexer($indexer->getName());

        $this->sendOrHandle($message, $useQueue);

        return $message;
    }
}
