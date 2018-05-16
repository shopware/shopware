<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Content\Product\Struct\ProductBasicStruct as ApiBasicStruct;


class StorefrontProductBasicStruct extends ApiBasicStruct implements StorefrontProductBasicInterface
{
    use StorefrontProductBasicTrait;
}
