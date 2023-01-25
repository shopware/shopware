<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class ImageType extends MediaType
{
    final public const ANIMATED = 'animated';
    final public const TRANSPARENT = 'transparent';
    final public const VECTOR_GRAPHIC = 'vectorGraphic';
    final public const ICON = 'image/x-icon';

    protected $name = 'IMAGE';
}
