<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('content')]
final class GenerateThumbnailsHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ThumbnailService $thumbnailService,
        private readonly EntityRepository $mediaRepository
    ) {
    }

    public function __invoke(GenerateThumbnailsMessage|UpdateThumbnailsMessage $msg): void
    {
        if (Feature::isActive('v6.6.0.0')) {
            $context = $msg->getContext();
        } else {
            $context = $msg->readContext();
        }

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
}
