<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

interface UrlGeneratorInterface
{
    public function getMediaUrl(string $filename, string $mimeType, bool $absolute = true): string;

    public function getThumbnailUrl(string $filename, string $mimeType, int $width, int $height, bool $isHighDpi = false, bool $absolute = true): string;
}
