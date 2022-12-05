<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 *
 * @internal
 *
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - EntityIndexerRegistry will not implement EventSubscriberInterface anymore, it will also become final and internal in v6.5.0
 */
class EntityIndexerRegistry implements EventSubscriberInterface, MessageSubscriberInterface
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
     * @var iterable<EntityIndexer>
     */
    private iterable $indexer;

    private MessageBusInterface $messageBus;

    private bool $working = false;

    private EventDispatcherInterface $dispatcher;

    /**
     * @internal
     *
     * @param iterable<EntityIndexer> $indexer
     */
    public function __construct(iterable $indexer, MessageBusInterface $messageBus, EventDispatcherInterface $dispatcher)
    {
        $this->indexer = $indexer;
        $this->messageBus = $messageBus;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param EntityIndexingMessage|IterateEntityIndexerMessage $message
     */
    public function __invoke($message): void
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

    /**
     * @deprecated tag:v6.5.0 - reason:remove-subscriber - will be removed in v6.5.0, event handling is done in `EntityIndexingSubscriber`
     */
    public static function getSubscribedEvents(): array
    {
        return [];
    }

    /**
     * @return iterable<class-string<AsyncMessageInterface>>
     */
    public static function getHandledMessages(): iterable
    {
        yield EntityIndexingMessage::class;
        yield IterateEntityIndexerMessage::class;
    }

    /**
     * @param list<string> $skip
     * @param list<string> $only
     */
    public function index(bool $useQueue, array $skip = [], array $only = []): void
    {
        foreach ($this->indexer as $indexer) {
            if (\in_array($indexer->getName(), $skip, true)) {
                continue;
            }

            if (\count($only) > 0 && !\in_array($indexer->getName(), $only, true)) {
                continue;
            }

            $offset = null;

            $this->dispatcher->dispatch(new ProgressStartedEvent($indexer->getName(), $indexer->getTotal()));

            while ($message = $indexer->iterate($offset)) {
                $message->setIndexer($indexer->getName());
                $message->addSkip(...$skip);

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

        $useQueue = $this->useQueue($context);

        foreach ($this->indexer as $indexer) {
            $message = $indexer->update($event);

            if (!$message) {
                continue;
            }

            $message->setIndexer($indexer->getName());
            self::addSkips($message, $context);

            $this->sendOrHandle($message, $useQueue);
        }

        $this->working = false;
    }

    public static function addSkips(EntityIndexingMessage $message, Context $context): void
    {
        if (!$context->hasExtension(self::EXTENSION_INDEXER_SKIP)) {
            return;
        }
        /** @var ArrayStruct<string, mixed> $skip */
        $skip = $context->getExtension(self::EXTENSION_INDEXER_SKIP);

        $message->addSkip(...$skip->all());
    }

    /**
     * @param list<string> $indexer
     * @param list<string> $skip
     */
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
        $this->__invoke($message);
    }

    /**
     * @param array<string, string>|null $offset
     * @param list<string> $skip
     */
    private function iterateIndexer(string $name, ?array $offset, bool $useQueue, array $skip): ?EntityIndexingMessage
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
        $message->addSkip(...$skip);

        $this->sendOrHandle($message, $useQueue);

        return $message;
    }
}
