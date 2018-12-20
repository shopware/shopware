<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

use Shopware\Core\Framework\Struct\Collection;

class PriceCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Price::class;
    }
}
