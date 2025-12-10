<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class VideoCoverLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MediaEvents::MEDIA_LOADED_EVENT => 'addVideoCoverExtension',
        ];
    }

    /**
     * @param EntityLoadedEvent<MediaEntity> $event
     */
    public function addVideoCoverExtension(EntityLoadedEvent $event): void
    {
        $coverIds = [];

        foreach ($event->getEntities() as $media) {
            $coverMediaId = $this->getCoverMediaId($media);

            if ($coverMediaId === null) {
                continue;
            }

            $coverIds[$coverMediaId] = $coverMediaId;
        }

        if ($coverIds === []) {
            return;
        }

        $criteria = (new Criteria(array_values($coverIds)))
            ->addAssociation('thumbnails');

        $covers = $this->mediaRepository->search($criteria, $event->getContext())->getEntities();

        foreach ($event->getEntities() as $media) {
            $coverMediaId = $this->getCoverMediaId($media);

            if ($coverMediaId === null) {
                continue;
            }

            $coverMedia = $covers->get($coverMediaId);

            if ($coverMedia === null) {
                continue;
            }

            $media->addExtension('videoCoverMedia', $coverMedia);
        }
    }

    private function getCoverMediaId(MediaEntity $media): ?string
    {
        $metaData = $media->getMetaData();

        if (!\is_array($metaData)) {
            return null;
        }

        return $metaData['video']['coverMediaId'] ?? null;
    }
}
