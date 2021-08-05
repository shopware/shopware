<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Event\MediaFolderIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MediaFolderIndexer extends EntityIndexer
{
    public const CHILD_COUNT_UPDATER = 'media_folder.child-count';

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $folderRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ChildCountUpdater
     */
    private $childCountUpdater;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        ChildCountUpdater $childCountUpdater
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->folderRepository = $repository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->childCountUpdater = $childCountUpdater;
    }

    public function getName(): string
    {
        return 'media_folder.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->folderRepository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new MediaIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(MediaFolderDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        $updates = array_merge($updates, $this->fetchChildren($updates));

        return new MediaIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_filter(array_unique($ids));

        if (empty($ids)) {
            return;
        }

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE media_folder SET media_folder_configuration_id = :configId WHERE id = :id')
        );

        foreach ($ids as $id) {
            $folder = $this->connection->fetchAssoc(
                'SELECT LOWER(HEX(child.id)) as id,
                       child.use_parent_configuration,
                       LOWER(HEX(child.media_folder_configuration_id)) as media_folder_configuration_id,
                       LOWER(HEX(parent.media_folder_configuration_id)) AS parent_configuration_id
                FROM media_folder child
                    LEFT JOIN media_folder as parent
                        ON parent.id = child.parent_id
                WHERE child.id = :id',
                ['id' => Uuid::fromHexToBytes($id)]
            );

            if (empty($folder)) {
                continue;
            }

            $configId = $folder['media_folder_configuration_id'];
            if ($folder['use_parent_configuration'] && $folder['parent_configuration_id']) {
                $configId = $folder['parent_configuration_id'];
            }

            $update->execute([
                'id' => Uuid::fromHexToBytes($folder['id']),
                'configId' => Uuid::fromHexToBytes($configId),
            ]);
        }

        if ($message->allow(self::CHILD_COUNT_UPDATER)) {
            $this->childCountUpdater->update(MediaFolderDefinition::ENTITY_NAME, $ids, $message->getContext());
        }

        $this->eventDispatcher->dispatch(new MediaFolderIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    private function fetchChildren(array $parentIds): array
    {
        $childIds = $this->connection->fetchAll(
            'SELECT LOWER(HEX(id)) as id FROM media_folder WHERE parent_id IN (:ids) AND use_parent_configuration = 1',
            ['ids' => Uuid::fromHexToBytesList($parentIds)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $childIds = array_column($childIds, 'id');

        if (!empty($childIds)) {
            $childIds = array_merge($childIds, $this->fetchChildren($childIds));
        }

        return $childIds;
    }
}
