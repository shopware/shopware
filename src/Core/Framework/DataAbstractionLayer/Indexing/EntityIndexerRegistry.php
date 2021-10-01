<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EntityIndexerRegistry extends AbstractMessageHandler implements EventSubscriberInterface
{
    public const EXTENSION_INDEXER_SKIP = 'indexer-skip';

    /**
     * @deprecated tag:v6.5.0 - `$context->addExtension(EntityIndexerRegistry::USE_INDEXING_QUEUE, ...)` will be ignored, use `context->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE)` instead
     */
    public const USE_INDEXING_QUEUE = 'use-queue-indexing';

    /**
     * @deprecated tag:v6.5.0 - `$context->addExtension(EntityIndexerRegistry::DISABLE_INDEXING, ...)` will be ignored, use `context->addState(EntityIndexerRegistry::DISABLE_INDEXING)` instead
     */
    public const DISABLE_INDEXING = 'disable-indexing';

    /**
     * @var EntityIndexer[]
     */
    private iterable $indexer;

    private MessageBusInterface $messageBus;

    private bool $working = false;

    private EventDispatcherInterface $dispatcher;

    public function __construct(iterable $indexer, MessageBusInterface $messageBus, EventDispatcherInterface $dispatcher)
    {
        $this->indexer = $indexer;
        $this->messageBus = $messageBus;
        $this->dispatcher = $dispatcher;
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

    public function index(bool $useQueue, array $skip = []): void
    {
        foreach ($this->indexer as $indexer) {
            if (\in_array($indexer->getName(), $skip, true)) {
                continue;
            }

            $offset = null;

            $this->dispatcher->dispatch(new ProgressStartedEvent($indexer->getName(), $indexer->getTotal()));

            while ($message = $indexer->iterate($offset)) {
                $message->setIndexer($indexer->getName());
                $message->setSkip($skip);

                $this->sendOrHandle($message, $useQueue);

                $offset = $message->getOffset();

                try {
                    $count = \is_array($message->getData()) ? \count($message->getData()) : 1;
                    $this->dispatcher->dispatch(new ProgressAdvancedEvent($count));
                } catch (\Exception $e) {
                }
            }

            $this->dispatcher->dispatch(new ProgressFinishedEvent($indexer->getName()));
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $context = $event->getContext();

        if ($this->working) {
            return;
        }
        $this->working = true;

        if ($this->disabled($context)) {
            $this->working = false;

            return;
        }

        $skips = [];
        if ($context->hasExtension(self::EXTENSION_INDEXER_SKIP)) {
            /** @var ArrayStruct $skip */
            $skip = $context->getExtension(self::EXTENSION_INDEXER_SKIP);
            $skips = $skip->all();
        }

        $useQueue = $this->useQueue($context);

        foreach ($this->indexer as $indexer) {
            $message = $indexer->update($event);

            if (!$message) {
                continue;
            }

            $message->setIndexer($indexer->getName());
            $message->setSkip($skips);

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
            $next = $this->iterateIndexer($message->getIndexer(), $message->getOffset(), true, $message->getSkip());

            if (!$next) {
                return;
            }

            $this->messageBus->dispatch(new IterateEntityIndexerMessage($message->getIndexer(), $next->getOffset(), $message->getSkip()));
        }
    }

    public function sendIndexingMessage(array $indexer = [], array $skip = []): void
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
            if (\in_array($name, $skip, true)) {
                continue;
            }

            $this->messageBus->dispatch(new IterateEntityIndexerMessage($name, null, $skip));
        }
    }

    public function has(string $name): bool
    {
        return $this->getIndexer($name) !== null;
    }

    public function getIndexer(string $name): ?EntityIndexer
    {
        foreach ($this->indexer as $indexer) {
            if ($indexer->getName() === $name) {
                return $indexer;
            }
        }

        return null;
    }

    private function useQueue(Context $context): bool
    {
        return $context->hasExtension(self::USE_INDEXING_QUEUE) || $context->hasState(self::USE_INDEXING_QUEUE);
    }

    private function disabled(Context $context): bool
    {
        return $context->hasExtension(self::DISABLE_INDEXING) || $context->hasState(self::DISABLE_INDEXING);
    }

    private function sendOrHandle(EntityIndexingMessage $message, bool $useQueue): void
    {
        if ($useQueue || $message->forceQueue()) {
            $this->messageBus->dispatch($message);

            return;
        }
        $this->handle($message);
    }

    private function iterateIndexer(string $name, $offset, bool $useQueue, array $skip): ?EntityIndexingMessage
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
        $message->setSkip($skip);

        $this->sendOrHandle($message, $useQueue);

        return $message;
    }
}
