<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
readonly class ExternalThumbnailData
{
    public function __construct(
        public string $url,
        /**
         * @var int<1, max> $width
         */
        public int $width,
        /**
         * @var int<1, max> $height
         */
        public int $height
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        MediaUploadService::validateExternalUrl($this->url);

        if ($this->width <= 0) {
            throw MediaException::invalidDimension('width', $this->width);
        }

        if ($this->height <= 0) {
            throw MediaException::invalidDimension('height', $this->height);
        }
    }
}
