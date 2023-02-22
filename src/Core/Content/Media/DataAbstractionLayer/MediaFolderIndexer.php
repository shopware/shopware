<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Event\MediaFolderIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('content')]
class MediaFolderIndexer extends EntityIndexer
{
    final public const CHILD_COUNT_UPDATER = 'media_folder.child-count';
    final public const TREE_UPDATER = 'media_folder.tree';

    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $folderRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ChildCountUpdater $childCountUpdater,
        private readonly TreeUpdater $treeUpdater
    ) {
    }

    public function getName(): string
    {
        return 'media_folder.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
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
        $mediaFolderEvent = $event->getEventByEntityName(MediaFolderDefinition::ENTITY_NAME);

        if (empty($updates) || !$mediaFolderEvent) {
            return null;
        }

        $idsWithChangedParentIds = [];
        foreach ($mediaFolderEvent->getWriteResults() as $result) {
            $payload = $result->getPayload();
            if (\array_key_exists('parentId', $payload)) {
                $idsWithChangedParentIds[] = $payload['id'];
            }
        }

        if ($idsWithChangedParentIds !== []) {
            $this->treeUpdater->batchUpdate(
                $idsWithChangedParentIds,
                MediaFolderDefinition::ENTITY_NAME,
                $event->getContext()
            );
        }

        $updates = array_values(array_merge($updates, $this->fetchChildren($updates), $this->getParentIds($updates)));

        return new MediaIndexingMessage($updates, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $context = $message->getContext();

        $ids = $message->getData();
        $ids = array_filter(array_unique($ids));

        if (empty($ids)) {
            return;
        }

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE media_folder SET media_folder_configuration_id = :configId WHERE id = :id')
        );

        foreach ($ids as $id) {
            $folder = $this->connection->fetchAssociative(
                'SELECT LOWER(HEX(child.id)) as id,
                       LOWER(HEX(parent.media_folder_configuration_id)) AS parent_configuration_id
                FROM media_folder child
                    LEFT JOIN media_folder as parent
                        ON parent.id = child.parent_id
                WHERE child.id = :id
                    AND child.media_folder_configuration_id != parent.media_folder_configuration_id
                    AND child.use_parent_configuration = 1',
                ['id' => Uuid::fromHexToBytes($id)]
            );

            if (empty($folder)) {
                continue;
            }

            $children = $this->fetchChildren([$id]);

            foreach (array_merge([$id], $children) as $folderId) {
                $update->execute([
                    'id' => Uuid::fromHexToBytes($folderId),
                    'configId' => Uuid::fromHexToBytes($folder['parent_configuration_id']),
                ]);
            }
        }

        if ($message->allow(self::CHILD_COUNT_UPDATER)) {
            $this->childCountUpdater->update(MediaFolderDefinition::ENTITY_NAME, $ids, $message->getContext());
        }

        if (!empty($children) && $message->allow(self::TREE_UPDATER)) {
            $this->treeUpdater->batchUpdate($children, MediaFolderDefinition::ENTITY_NAME, $context);
        }

        $this->eventDispatcher->dispatch(new MediaFolderIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getOptions(): array
    {
        return [
            self::CHILD_COUNT_UPDATER,
            self::TREE_UPDATER,
        ];
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->folderRepository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param array<string> $parentIds
     */
    private function fetchChildren(array $parentIds): array
    {
        $childIds = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id FROM media_folder WHERE parent_id IN (:ids) AND use_parent_configuration = 1',
            ['ids' => Uuid::fromHexToBytesList($parentIds)],
            ['ids' => ArrayParameterType::STRING]
        );

        $childIds = array_column($childIds, 'id');

        if (!empty($childIds)) {
            $childIds = array_merge($childIds, $this->fetchChildren($childIds));
        }

        return $childIds;
    }

    /**
     * @return array<string>
     */
    private function getParentIds(array $ids): array
    {
        /** @var array<string> $parentIds */
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(media_folder.parent_id)) as id FROM media_folder WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        return array_unique(array_filter($parentIds));
    }
}
