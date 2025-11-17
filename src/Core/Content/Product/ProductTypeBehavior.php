<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductTypeBehavior
{
    public function __construct(
        public readonly bool $exportable = true,
        public readonly bool $downloadable = false,
        public readonly bool $shippable = false,
    ) {
    }
}
