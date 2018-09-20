<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use Shopware\Core\Content\Media\MediaStruct;

interface UrlGeneratorInterface
{
    public function getAbsoluteMediaUrl(MediaStruct $media): string;

    public function getRelativeMediaUrl(MediaStruct $media): string;

    public function getAbsoluteThumbnailUrl(MediaStruct $media, int $width, int $height, bool $isHighDpi = false): string;

    public function getRelativeThumbnailUrl(MediaStruct $media, int $width, int $height, bool $isHighDpi = false): string;
}
