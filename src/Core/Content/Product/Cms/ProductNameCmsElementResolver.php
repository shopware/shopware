<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;

class ProductNameCmsElementResolver extends TextCmsElementResolver
{
    public function getType(): string
    {
        return 'product-name';
    }
}
