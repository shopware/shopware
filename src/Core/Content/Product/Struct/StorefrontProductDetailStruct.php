<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Content\Product\Struct\ProductDetailStruct as ApiProductDetailStruct;

class StorefrontProductDetailStruct extends ApiProductDetailStruct implements StorefrontProductBasicInterface
{
    use StorefrontProductBasicTrait;
}
