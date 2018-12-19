<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Struct\Collection;

class PriceDefinitionCollection extends Collection
{
    public function get($key): ? PriceDefinitionInterface
    {
        $key = (int) $key;

        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    protected function getExpectedClass(): ?string
    {
        return PriceDefinitionInterface::class;
    }
}
