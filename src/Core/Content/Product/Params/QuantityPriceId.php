<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Params;

use Shopware\Core\Framework\Struct\Extendable;

class QuantityPriceId
{
    use Extendable;

    public function __construct(public string $id, public int $quantity) {}
}
