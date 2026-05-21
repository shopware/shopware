<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail\DTO;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final readonly class ThumbnailImage
{
    public function __construct(
        public object $image
    ) {
    }
}
