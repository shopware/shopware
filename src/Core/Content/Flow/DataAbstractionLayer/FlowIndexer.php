<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\DataAbstractionLayer;

use Shopware\Core\Content\Flow\Events\FlowIndexerEvent;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowIndexer extends EntityIndexer
{
    private IteratorFactory $iteratorFactory;

    private EntityRepositoryInterface $repository;

    private FlowPayloadUpdater $payloadUpdater;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        FlowPayloadUpdater $payloadUpdater,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->payloadUpdater = $payloadUpdater;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName(): string
    {
        return 'flow.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new FlowIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(FlowDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        $this->handle(new FlowIndexingMessage(array_values($updates), null, $event->getContext()));

        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $this->payloadUpdater->update($ids);

        $this->eventDispatcher->dispatch(new FlowIndexerEvent($ids, $message->getContext()));
    }
}
