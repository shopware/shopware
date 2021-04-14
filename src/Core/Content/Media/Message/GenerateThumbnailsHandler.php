<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

class GenerateThumbnailsHandler extends AbstractMessageHandler
{
    private ThumbnailService $thumbnailService;

    private EntityRepositoryInterface $mediaRepository;

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

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');
        $criteria->addFilter(new EqualsAnyFilter('media.id', $msg->getMediaIds()));

        /** @var MediaCollection $entities */
        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();

        $this->thumbnailService->generate($entities, $context);
    }

    public static function getHandledMessages(): iterable
    {
        return [
            GenerateThumbnailsMessage::class,
            UpdateThumbnailsMessage::class,
        ];
    }
}
