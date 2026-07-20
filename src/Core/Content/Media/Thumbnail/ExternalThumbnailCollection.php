<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ExternalThumbnailData>
 */
#[Package('discovery')]
class ExternalThumbnailCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ExternalThumbnailData::class;
    }
}
