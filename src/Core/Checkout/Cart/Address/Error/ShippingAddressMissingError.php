<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingAddressMissingError extends AddressMissingError
{
    private const KEY = 'shipping-address-missing';

    public function __construct()
    {
        parent::__construct('The customer has no active shipping address.');
    }

    public function getId(): string
    {
        return self::KEY;
    }
}
