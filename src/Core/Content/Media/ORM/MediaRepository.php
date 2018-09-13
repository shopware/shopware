<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\ORM;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
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

class MediaRepository extends EntityRepository
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var MediaThumbnailRepository
     */
    private $thumbnailRepository;

    public function __construct(
        string $definition,
        EntityReaderInterface $reader,
        VersionManager $versionManager,
        EntitySearcherInterface $searcher,
        EntityAggregatorInterface $aggregator,
        EventDispatcherInterface $eventDispatcher,
        UrlGeneratorInterface $urlGenerator,
        FilesystemInterface $filesystem,
        MediaThumbnailRepository $thumbnailRepository
    ) {
        parent::__construct(
            $definition,
            $reader,
            $versionManager,
            $searcher,
            $aggregator,
            $eventDispatcher
        );

        $this->filesystem = $filesystem;
        $this->urlGenerator = $urlGenerator;
        $this->thumbnailRepository = $thumbnailRepository;
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        $criteria = new ReadCriteria($ids);
        $affectedMedia = $this->search($criteria, $context);

        if ($affectedMedia->getEntities()->count() === 0) {
            $event = EntityWrittenContainerEvent::createWithDeletedEvents([], $context, []);
            $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

            return $event;
        }

        foreach ($affectedMedia->getEntities() as $mediaStruct) {
            if (!$mediaStruct->gethasFile()) {
                continue;
            }

            $mediaPath = $this->urlGenerator->getRelativeMediaUrl(
                $mediaStruct->getId(),
                $mediaStruct->getFileExtension()
            );

            try {
                $this->filesystem->delete($mediaPath);
            } catch (FileNotFoundException $e) {
                //ignore file is already deleted
            }

            $this->thumbnailRepository->deleteCascadingFromMedia($mediaStruct, $context);
        }

        return parent::delete($ids, $context);
    }
}
