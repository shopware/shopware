<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\AbstractPathGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('content')]
class MediaIndexer extends EntityIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly EntityRepository $thumbnailRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractPathGenerator $pathGenerator
    ) {
    }

    public function getName(): string
    {
        return 'media.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
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

        $context = $message->getContext();

        $this->updateThumbnailsPath($context, $ids);

        $this->updateThumbnailsRo($context, $ids);

        $this->setMediaPaths($context, $ids);

        $this->eventDispatcher->dispatch(new MediaIndexerEvent($ids, $context, $message->getSkip()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }

    /**
     * @param string[] $ids
     */
    private function updateThumbnailsPath(Context $context, array $ids): void
    {
        $mediaThumbnailIdsWithMissingPaths = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) from media_thumbnail WHERE media_id IN (:ids) AND path IS NULL',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        if (\count($mediaThumbnailIdsWithMissingPaths) === 0) {
            return;
        }

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `media_thumbnail` SET path = :path WHERE id = :id')
        );

        //get all media_thumbnails with missingPaths
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $mediaThumbnailIdsWithMissingPaths));
        $criteria->addAssociation('media');

        $all = $this->thumbnailRepository
            ->search($criteria, $context)
            ->getEntities();

        /** @var MediaThumbnailEntity $mediaThumbnail */
        foreach ($all as $mediaThumbnail) {
            $media = $mediaThumbnail->getMedia();

            if ($media === null) {
                continue;
            }

            $query->execute([
                'path' => $this->pathGenerator->generatePath($media, $mediaThumbnail),
                'id' => Uuid::fromHexToBytes($mediaThumbnail->getId()),
            ]);
        }
    }

    /**
     * @param string[] $ids
     */
    private function updateThumbnailsRo(Context $context, array $ids): void
    {
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `media` SET thumbnails_ro = :thumbnails_ro WHERE id = :id')
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('mediaId', $ids));

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
    }

    /**
     * @param string[] $ids
     */
    private function setMediaPaths(Context $context, array $ids): void
    {
        $mediaIdsWithMissingPaths = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) from media WHERE id IN (:ids) AND path IS NULL AND file_name IS NOT NULL',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        if (\count($mediaIdsWithMissingPaths) === 0) {
            return;
        }

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `media` SET path = :path WHERE id = :id')
        );

        //get all media with missingPaths
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $mediaIdsWithMissingPaths));

        $all = $this->repository
            ->search($criteria, $context)
            ->getEntities();

        /** @var MediaEntity $media */
        foreach ($all as $media) {
            $query->execute([
                'path' => $this->pathGenerator->generatePath($media),
                'id' => Uuid::fromHexToBytes($media->getId()),
            ]);
        }
    }
}
