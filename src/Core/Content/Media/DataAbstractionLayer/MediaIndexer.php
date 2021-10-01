<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MediaIndexer extends EntityIndexer
{
    private IteratorFactory $iteratorFactory;

    private EntityRepositoryInterface $repository;

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepositoryInterface $thumbnailRepository;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        EntityRepositoryInterface $thumbnailRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->thumbnailRepository = $thumbnailRepository;
    }

    public function getName(): string
    {
        return 'media.indexer';
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

        return new MediaIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(MediaDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new MediaIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('mediaId', $ids));

        $context = $message->getContext();

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `media` SET thumbnails_ro = :thumbnails_ro WHERE id = :id')
        );

        $all = $this->thumbnailRepository
            ->search($criteria, $context)
            ->getEntities();

        foreach ($ids as $id) {
            $thumbnails = $all->filterByProperty('mediaId', $id);

            $query->execute([
                'thumbnails_ro' => serialize($thumbnails),
                'id' => Uuid::fromHexToBytes($id),
            ]);
        }

        $this->eventDispatcher->dispatch(new MediaIndexerEvent($ids, $context, $message->getSkip()));
    }
}
