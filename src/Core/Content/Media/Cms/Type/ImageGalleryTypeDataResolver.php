<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms\Type;

/**
 * @package content
 */
class ImageGalleryTypeDataResolver extends ImageSliderTypeDataResolver
{
    public function getType(): string
    {
        return 'image-gallery';
    }
}
