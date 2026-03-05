<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms\Type;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class MediaGalleryTypeDataResolver extends ImageSliderTypeDataResolver
{
    public function getType(): string
    {
        return 'media-gallery';
    }
}
