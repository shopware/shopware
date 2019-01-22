<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
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
        $affectedMedia = $this->read(new Criteria($this->getRawIds($ids)), $context);

        if ($affectedMedia->count() === 0) {
            $event = EntityWrittenContainerEvent::createWithDeletedEvents([], $context, []);
            $this->eventDispatcher->dispatch(EntityWrittenContainerEvent::NAME, $event);

            return $event;
        }

        foreach ($affectedMedia as $mediaEntity) {
            if (!$mediaEntity->hasFile()) {
                continue;
            }

            $mediaPath = $this->urlGenerator->getRelativeMediaUrl($mediaEntity);

            try {
                $this->filesystem->delete($mediaPath);
            } catch (FileNotFoundException $e) {
                //ignore file is already deleted
            }

            $this->thumbnailRepository->deleteCascadingFromMedia($mediaEntity, $context);
        }

        return parent::delete($ids, $context);
    }

    private function getRawIds(array $ids)
    {
        return array_map(
            function ($idArray) {
                return $idArray['id'];
            },
            $ids
        );
    }
}
