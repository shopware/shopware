<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

class GenerateThumbnailsHandler implements MessageSubscriberInterface
{
    /**
     * @var ThumbnailService
     */
    private $thumbnailService;
    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    public function __construct(ThumbnailService $thumbnailService, EntityRepositoryInterface $mediaRepository)
    {
        $this->thumbnailService = $thumbnailService;
        $this->mediaRepository = $mediaRepository;
    }

    public function __invoke(GenerateThumbnailsMessage $msg)
    {
        $context = $msg->readContext();

        $entities = $this->getMediaEntities($msg, $context);

        foreach ($entities as $media) {
            $this->thumbnailService->updateThumbnails($media, $context);
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [GenerateThumbnailsMessage::class];
    }

    private function getMediaEntities(GenerateThumbnailsMessage $msg, Context $context): MediaCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('media.id', $msg->getMediaIds()));
        /** @var MediaCollection $entities */
        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();

        return $entities;
    }
}
