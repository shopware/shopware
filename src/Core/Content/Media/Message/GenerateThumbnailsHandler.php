<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

class GenerateThumbnailsHandler extends AbstractMessageHandler
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

    /**
     * @param GenerateThumbnailsMessage|UpdateThumbnailsMessage $msg
     */
    public function handle($msg): void
    {
        $context = $msg->readContext();

        $entities = $this->getMediaEntities($msg, $context);

        if ($msg instanceof UpdateThumbnailsMessage) {
            $this->updateThumbnailsForEntities($entities, $context);
        } else {
            $this->generateThumbnailsForEntities($entities, $context);
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [
            GenerateThumbnailsMessage::class,
            UpdateThumbnailsMessage::class,
        ];
    }

    private function getMediaEntities(GenerateThumbnailsMessage $msg, Context $context): MediaCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('media.id', $msg->getMediaIds()));
        /** @var MediaCollection $entities */
        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();

        return $entities;
    }

    private function updateThumbnailsForEntities(MediaCollection $entities, Context $context): void
    {
        foreach ($entities as $media) {
            $this->thumbnailService->generateThumbnails($media, $context);
        }
    }

    private function generateThumbnailsForEntities(MediaCollection $entities, Context $context): void
    {
        foreach ($entities as $media) {
            $this->thumbnailService->generateThumbnails($media, $context);
        }
    }
}
