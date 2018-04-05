<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Product;

use Shopware\Api\Product\Struct\ProductBasicStruct as ApiBasicStruct;

class ProductBasicStruct extends ApiBasicStruct implements StorefrontProductBasicInterface
{
    use StorefrontProductBasicTrait;
}
