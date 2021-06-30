<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\System\SalesChannel\Event\SalesChannelIndexerEvent;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SalesChannelIndexer extends EntityIndexer
{
    public const MANY_TO_MANY_UPDATER = 'sales_channel.many-to-many';

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ManyToManyIdFieldUpdater
     */
    private $manyToManyUpdater;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        EventDispatcherInterface $eventDispatcher,
        ManyToManyIdFieldUpdater $manyToManyUpdater
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
        $this->manyToManyUpdater = $manyToManyUpdater;
    }

    public function getName(): string
    {
        return 'sales_channel.indexer';
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

        return new SalesChannelIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(SalesChannelDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new SalesChannelIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        if ($message->allow(self::MANY_TO_MANY_UPDATER)) {
            $this->manyToManyUpdater->update(SalesChannelDefinition::ENTITY_NAME, $ids, $message->getContext());
        }

        $this->eventDispatcher->dispatch(new SalesChannelIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }
}
