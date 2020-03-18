<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
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
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

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
        Connection $connection,
        CacheClearer $cacheClearer,
        EventDispatcherInterface $eventDispatcher,
        ManyToManyIdFieldUpdater $manyToManyUpdater
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->cacheClearer = $cacheClearer;
        $this->eventDispatcher = $eventDispatcher;
        $this->manyToManyUpdater = $manyToManyUpdater;
    }

    public function getName(): string
    {
        return 'sales_channel.indexer';
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage($ids, $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(SalesChannelDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new EntityIndexingMessage($updates, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        if (empty($ids)) {
            return;
        }

        $this->manyToManyUpdater->update(SalesChannelDefinition::ENTITY_NAME, $ids, $message->getContext());

        $this->eventDispatcher->dispatch(new SalesChannelIndexerEvent($ids, $message->getContext()));

        $this->cacheClearer->invalidateIds($ids, SalesChannelDefinition::ENTITY_NAME);
    }
}
