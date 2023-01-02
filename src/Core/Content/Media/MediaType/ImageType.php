<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\MediaType;

use Shopware\Core\Framework\Log\Package;
/**
 * @package content
 */
#[Package('content')]
class ImageType extends MediaType
{
    public const ANIMATED = 'animated';
    public const TRANSPARENT = 'transparent';
    public const VECTOR_GRAPHIC = 'vectorGraphic';
    public const ICON = 'image/x-icon';

    protected $name = 'IMAGE';
}
