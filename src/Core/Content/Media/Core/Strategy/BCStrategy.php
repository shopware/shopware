<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Strategy;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprected tag:v6.6.0 - reason:factory-for-deprecation - Just for BC compatibility with the old path generator
 */
#[Package('content')]
class BCStrategy extends AbstractMediaPathStrategy
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $thumbnailRepository,
        private readonly UrlGeneratorInterface $generator
    ) {
    }

    public function generate(array $locations): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Implement your own AbstractMediaPathStrategy instead'),
        );

        $mediaIds = [];
        $thumbnailIds = [];

        foreach ($locations as $location) {
            if ($location instanceof MediaLocationStruct) {
                $mediaIds[] = $location->id;
            } elseif ($location instanceof ThumbnailLocationStruct) {
                $thumbnailIds[] = $location->id;
            }
        }

        // sadly, we have no access to any kind of context here, so we have to create a default one.
        // But this is not a problem, because the file storage path can not depend on the context values anyway
        $context = Context::createDefaultContext();

        $mapping = [];
        if (!empty($mediaIds)) {
            $collection = $this->mediaRepository->search(new Criteria($mediaIds), $context);

            /** @var MediaEntity $media */
            foreach ($collection as $media) {
                $mapping[$media->getId()] = $this->generator->getRelativeMediaUrl($media);
            }
        }

        if (!empty($thumbnailIds)) {
            $criteria = new Criteria($thumbnailIds);
            $criteria->addAssociation('media');

            $thumbnails = $this->thumbnailRepository->search($criteria, $context);

            /** @var MediaThumbnailEntity $thumbnail */
            foreach ($thumbnails as $thumbnail) {
                if (!$thumbnail->getMedia()) {
                    continue;
                }
                $mapping[$thumbnail->getId()] = $this->generator->getRelativeThumbnailUrl($thumbnail->getMedia(), $thumbnail);
            }
        }

        $result = [];
        foreach ($locations as $key => $location) {
            $result[$key] = $mapping[$location->id];
        }

        return $result;
    }

    public function name(): string
    {
        return 'bc';
    }
}
