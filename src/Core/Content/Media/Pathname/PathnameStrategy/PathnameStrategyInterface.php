<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;

/**
 * Generate path components of media urls/filesystem paths
 *
 * providing data for the default format: __HASH__/__BUSTER__/__PHYSICAL_FILE_NAME_WITH_EXTENSION
 *
 * Important:
 *   * If an empty string is returned, the data will be striped
 *   * You must not return leading or trailing slashes
 */
interface PathnameStrategyInterface
{
    public function getName(): string;

    /**
     * Generate a hash, missing from url if omitted
     */
    public function generatePathHash(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string;

    /**
     * Generate the cache buster part of the path, missing from url if omitted
     */
    public function generatePathCacheBuster(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string;

    /**
     * Generate the filename
     */
    public function generatePhysicalFilename(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): string;
}
