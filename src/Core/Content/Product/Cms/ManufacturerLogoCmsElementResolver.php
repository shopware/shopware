<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;

class ManufacturerLogoCmsElementResolver extends ImageCmsElementResolver
{
    public function getType(): string
    {
        return 'manufacturer-logo';
    }
}
