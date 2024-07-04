<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\FullEntityIndexerMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[AsMessageHandler]
#[Package('core')]
class EntityIndexerRegistry
{
    final public const EXTENSION_INDEXER_SKIP = 'indexer-skip';

    final public const USE_INDEXING_QUEUE = 'use-queue-indexing';

    final public const DISABLE_INDEXING = 'disable-indexing';

    private bool $working = false;

    /**
     * @internal
     *
     * @param iterable<EntityIndexer> $indexer
     */
    public function __construct(
        private readonly iterable $indexer,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * @internal
     */
    public function __invoke(EntityIndexingMessage|IterateEntityIndexerMessage|FullEntityIndexerMessage $message): void
    {
        if ($message instanceof FullEntityIndexerMessage) {
            $this->index(true, $message->getSkip(), $message->getOnly());

            return;
        }

        if ($message instanceof EntityIndexingMessage) {
            $indexer = $this->getIndexer($message->getIndexer());

            if ($indexer) {
                $indexer->handle($message);
            }

            return;
        }

        $next = $this->iterateIndexer($message->getIndexer(), $message->getOffset(), $message->getSkip());

        if (!$next) {
            return;
        }

        $this->messageBus->dispatch(new IterateEntityIndexerMessage($message->getIndexer(), $next->getOffset(), $message->getSkip()));
    }

    /**
     * @param list<string> $skip
     * @param list<string> $only
     */
    public function index(bool $useQueue, array $skip = [], array $only = [], bool $postUpdate = false): void
    {
        foreach ($this->indexer as $indexer) {
            // when we are not in post update mode, skip all post update indexer
            if (!$postUpdate && $indexer instanceof PostUpdateIndexer) {
                continue;
            }
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
                $message->isFullIndexing = true;

                $this->sendOrHandle($message, $useQueue);

                $offset = $message->getOffset();

                try {
                    $count = \is_array($message->getData()) ? \count($message->getData()) : 1;
                    $this->dispatcher->dispatch(new ProgressAdvancedEvent($count));
                } catch (\Exception) {
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
            if ($indexer instanceof PostUpdateIndexer) {
                continue;
            }

            $message = $indexer->update($event);

            if (!$message) {
                continue;
            }

            $message->setIndexer($indexer->getName());
            $message->isFullIndexing = false;
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
        $skip = $context->getExtension(self::EXTENSION_INDEXER_SKIP);
        if (!$skip instanceof ArrayEntity) {
            return;
        }

        $message->addSkip(...$skip->get('skips'));
    }

    /**
     * @param list<string> $indexer
     * @param array<string> $skip
     */
    public function sendIndexingMessage(array $indexer = [], array $skip = [], bool $postUpdate = false): void
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
            $instance = $this->getIndexer($name);

            // skip "one-time" indexer which should only be triggered after an update
            if (!$postUpdate && $instance instanceof PostUpdateIndexer) {
                continue;
            }
            if (\in_array($name, $skip, true)) {
                continue;
            }

            $this->messageBus->dispatch(new IterateEntityIndexerMessage($name, null, $skip));
        }
    }

    /**
     * @param list<string> $skip
     * @param list<string> $only
     */
    public function sendFullIndexingMessage(array $skip = [], array $only = []): void
    {
        $this->messageBus->dispatch(new FullEntityIndexerMessage($skip, $only));
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
        return $context->hasState(self::USE_INDEXING_QUEUE);
    }

    private function disabled(Context $context): bool
    {
        return $context->hasState(self::DISABLE_INDEXING);
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
     * @param array<string> $skip
     * @param array{offset: int|null}|null $offset
     */
    private function iterateIndexer(string $name, ?array $offset, array $skip): ?EntityIndexingMessage
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
        $message->isFullIndexing = true;

        $this->sendOrHandle($message, true);

        return $message;
    }
}
