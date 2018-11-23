<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Rule\Rule;

interface PriceDefinitionInterface
{
    public function getFilter(): ?Rule;
}
