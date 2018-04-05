<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Product;

use Shopware\Api\Product\Struct\ProductDetailStruct as ApiProductDetailStruct;

class ProductDetailStruct extends ApiProductDetailStruct implements StorefrontProductBasicInterface
{
    use StorefrontProductBasicTrait;
}
