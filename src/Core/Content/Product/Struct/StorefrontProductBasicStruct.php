<?php declare(strict_types=1);

namespace Shopware\Content\Product\Struct;

use Shopware\Content\Product\Struct\ProductBasicStruct as ApiBasicStruct;
use Shopware\Content\Product\Struct\StorefrontProductBasicTrait;
use Shopware\Content\Product\Struct\StorefrontProductBasicInterface;

class StorefrontProductBasicStruct extends ApiBasicStruct implements StorefrontProductBasicInterface
{
    use StorefrontProductBasicTrait;
}
