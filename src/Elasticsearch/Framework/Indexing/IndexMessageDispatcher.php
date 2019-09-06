<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IndexMessageDispatcher
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    public function __construct(IteratorFactory $iteratorFactory, EventDispatcherInterface $eventDispatcher, MessageBusInterface $messageBus)
    {
        $this->iteratorFactory = $iteratorFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->messageBus = $messageBus;
    }

    public function dispatchForAllEntities(string $index, EntityDefinition $definition, Context $context): int
    {
        $iterator = $this->iteratorFactory->createIterator($definition);

        $count = $iterator->fetchCount();

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent(sprintf('Start indexing elastic search for entity %s', $definition->getEntityName()), $count),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $ids = $this->makeIdsSerializable($ids);

            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(count($ids)),
                ProgressAdvancedEvent::NAME
            );

            $this->messageBus->dispatch(
                new IndexingMessage($ids, $index, $context, $definition->getEntityName())
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent(sprintf('Finished indexing elastic search for entity %s', $definition->getEntityName())),
            ProgressFinishedEvent::NAME
        );

        return $count;
    }

    public function dispatchForIds(array $ids, string $index, EntityDefinition $definition, Context $context): void
    {
        $ids = $this->makeIdsSerializable($ids);
        $this->messageBus->dispatch(
            new IndexingMessage($ids, $index, $context, $definition->getEntityName())
        );
    }

    private function makeIdsSerializable(array $ids): array
    {
        return array_values($ids);
    }
}
