<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface AddressErrorInterface
{
    public function getAddressId(): ?string;
}
