<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\ORM;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaThumbnailRepository extends EntityRepository
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(
        string $definition,
        EntityReaderInterface $reader,
        VersionManager $versionManager,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher,
        UrlGeneratorInterface $urlGenerator,
        FilesystemInterface $filesystem
    ) {
        parent::__construct(
            $definition,
            $reader,
            $versionManager,
            $searcher,
            $aggregator,
            $eventDispatcher
        );

        $this->urlGenerator = $urlGenerator;
        $this->filesystem = $filesystem;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $read = new ReadCriteria($ids);
        $read->addAssociation('media_thumbnail.media');

        /** @var MediaThumbnailCollection $thumbnailsSearch */
        $thumbnailsSearch = $this->search($read, $context)->getEntities();

        return $this->deleteFromCollection($thumbnailsSearch, $context);
    }

    public function deleteCascadingFromMedia(MediaStruct $mediaStruct, Context $context)
    {
        foreach ($mediaStruct->getThumbnails() as $thumbnail) {
            $thumbnail->setMedia($mediaStruct);
        }

        return $this->deleteFromCollection($mediaStruct->getThumbnails(), $context);
    }

    private function deleteFromCollection(MediaThumbnailCollection $thumbnails, Context $context): EntityWrittenContainerEvent
    {
        if ($thumbnails->count() === 0) {
            $event = EntityWrittenContainerEvent::createWithDeletedEvents([], $context, []);
            $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

            return $event;
        }

        $thumbnailIds = [];
        foreach ($thumbnails as $thumbnail) {
            $thumbnailIds[] = [
                'id' => $thumbnail->getId(),
            ];

            $relatedMedia = $thumbnail->getMedia();

            $thumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl(
                $relatedMedia->getId(),
                $relatedMedia->getFileExtension(),
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                $thumbnail->getHighDpi()
            );

            try {
                $this->filesystem->delete($thumbnailPath);
            } catch (FileNotFoundException $e) {
                //ignore file is already deleted
            }
        }

        return parent::delete($thumbnailIds, $context);
    }
}
