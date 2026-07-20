<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Service;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class VideoCoverService
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    public function assignCoverToVideo(string $videoMediaId, ?string $coverMediaId, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scopedContext) use ($videoMediaId, $coverMediaId): void {
            $video = $this->getMedia($videoMediaId, $scopedContext);
            if (!$this->isVideo($video)) {
                throw MediaException::mediaFileTypeNotSupported($videoMediaId, 'video');
            }

            $this->validateCoverMedia($coverMediaId, $scopedContext);

            $metaData = $this->buildUpdatedMetaData($video->getMetaData(), $coverMediaId);

            $this->mediaRepository->update([
                [
                    'id' => $videoMediaId,
                    'metaData' => $metaData,
                ],
            ], $scopedContext);
        });
    }

    private function validateCoverMedia(?string $coverMediaId, Context $context): void
    {
        if ($coverMediaId === null) {
            return;
        }

        $cover = $this->getMedia($coverMediaId, $context);
        if (!$this->isImage($cover)) {
            throw MediaException::mediaFileTypeNotSupported($coverMediaId, 'image');
        }
    }

    private function getMedia(string $id, Context $context): MediaEntity
    {
        $criteria = new Criteria([$id]);

        $media = $this->mediaRepository->search($criteria, $context)->getEntities()->get($id);

        if ($media === null) {
            throw MediaException::mediaNotFound($id);
        }

        return $media;
    }

    /**
     * @param array<string, mixed>|null $metaData
     *
     * @return array<string, mixed>|null
     */
    private function buildUpdatedMetaData(?array $metaData, ?string $coverMediaId): ?array
    {
        $nextMetaData = $metaData ? [...$metaData] : [];
        $videoMeta = \is_array($nextMetaData['video'] ?? null) ? $nextMetaData['video'] : [];

        if ($coverMediaId !== null) {
            $videoMeta['coverMediaId'] = $coverMediaId;
        } else {
            unset($videoMeta['coverMediaId']);
        }

        if ($videoMeta !== []) {
            $nextMetaData['video'] = $videoMeta;
        } else {
            unset($nextMetaData['video']);
        }

        return $nextMetaData === [] ? null : $nextMetaData;
    }

    private function isVideo(MediaEntity $media): bool
    {
        $mediaType = $media->getMediaType();

        if ($mediaType instanceof VideoType) {
            return true;
        }

        return \is_string($media->getMimeType()) && str_starts_with($media->getMimeType(), 'video/');
    }

    private function isImage(MediaEntity $media): bool
    {
        $mediaType = $media->getMediaType();

        if ($mediaType instanceof ImageType) {
            return true;
        }

        return \is_string($media->getMimeType()) && str_starts_with($media->getMimeType(), 'image/');
    }
}
