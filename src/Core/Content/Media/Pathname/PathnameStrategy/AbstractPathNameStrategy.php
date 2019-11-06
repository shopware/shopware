<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;

abstract class AbstractPathNameStrategy implements PathnameStrategyInterface
{
    public function generatePhysicalFilename(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): string
    {
        $filenameSuffix = '';
        if ($thumbnail !== null) {
            $filenameSuffix = sprintf('_%dx%d', $thumbnail->getWidth(), $thumbnail->getHeight());
        }

        $extension = $media->getFileExtension() ? '.' . $media->getFileExtension() : '';

        return $media->getFileName() . $filenameSuffix . $extension;
    }

    public function generatePathCacheBuster(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string
    {
        $uploadedAt = $media->getUploadedAt();

        if ($uploadedAt === null) {
            return null;
        }

        return (string) $uploadedAt->getTimestamp();
    }
}
