<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;

interface UrlGeneratorInterface
{
    public function getAbsoluteMediaUrl(MediaEntity $media): string;

    public function getRelativeMediaUrl(MediaEntity $media): string;

    public function getAbsoluteThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string;

    public function getRelativeThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string;
}
