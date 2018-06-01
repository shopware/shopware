<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Struct;

use Shopware\Core\Content\Product\Struct\ProductDetailStruct as ApiProductDetailStruct;

class StorefrontProductDetailStruct extends ApiProductDetailStruct implements StorefrontProductBasicInterface
{
    use StorefrontProductBasicTrait;
}
