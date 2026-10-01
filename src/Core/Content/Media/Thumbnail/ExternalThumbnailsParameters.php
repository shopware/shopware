<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ExternalThumbnailsParameters
{
    public function __construct(
        public readonly ExternalThumbnailCollection $thumbnails = new ExternalThumbnailCollection(),
    ) {
    }
}
