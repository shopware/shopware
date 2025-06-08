<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;

#[Package('checkout')]
interface FilterableInterface
{
    public function getFilter(): ?Rule;
}
