<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @package content
 *
 * @internal
 */
final class GenerateThumbnailsHandler implements MessageSubscriberInterface
{
    private ThumbnailService $thumbnailService;

    private EntityRepository $mediaRepository;

    /**
     * @internal
     */
    public function __construct(ThumbnailService $thumbnailService, EntityRepository $mediaRepository)
    {
        $this->thumbnailService = $thumbnailService;
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * @param GenerateThumbnailsMessage|UpdateThumbnailsMessage $msg
     */
    public function __invoke($msg): void
    {
        $context = $msg->readContext();

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolder.configuration.mediaThumbnailSizes');
        $criteria->addFilter(new EqualsAnyFilter('media.id', $msg->getMediaIds()));

        /** @var MediaCollection $entities */
        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();

        if ($msg instanceof UpdateThumbnailsMessage) {
            foreach ($entities as $media) {
                $this->thumbnailService->updateThumbnails($media, $context, $msg->isStrict());
            }
        } else {
            $this->thumbnailService->generate($entities, $context);
        }
    }

    /**
     * @return iterable<class-string<AsyncMessageInterface>>
     */
    public static function getHandledMessages(): iterable
    {
        yield GenerateThumbnailsMessage::class;
        yield UpdateThumbnailsMessage::class;
    }
}
