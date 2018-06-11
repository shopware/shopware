<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Struct;

use Shopware\Core\Content\Product\Struct\ProductBasicStruct as ApiBasicStruct;

class StorefrontProductBasicStruct extends ApiBasicStruct implements StorefrontProductBasicInterface
{
    use StorefrontProductBasicTrait;
}
