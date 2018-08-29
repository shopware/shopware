<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Util;

interface UrlGeneratorInterface
{
    public function getAbsoluteMediaUrl(string $filename, string $extension): string;

    public function getRelativeMediaUrl(string $filename, string $extension): string;

    public function getAbsoluteThumbnailUrl(string $filename, string $extension, int $width, int $height, bool $isHighDpi = false): string;

    public function getRelativeThumbnailUrl(string $filename, string $extension, int $width, int $height, bool $isHighDpi = false): string;
}
