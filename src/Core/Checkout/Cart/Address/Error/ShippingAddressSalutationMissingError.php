<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\ErrorRoute;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingAddressSalutationMissingError extends SalutationMissingError
{
    protected const KEY = parent::KEY . '-shipping-address';

    public function __construct(CustomerAddressEntity $address)
    {
        $this->message = sprintf(
            'A salutation needs to be defined for the shipping address "%s %s, %s %s".',
            $address->getFirstName(),
            $address->getLastName(),
            $address->getZipcode(),
            $address->getCity()
        );

        $this->parameters = [
            'addressId' => $address->getId(),
        ];

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getRoute(): ?ErrorRoute
    {
        return new ErrorRoute(
            'frontend.account.address.edit.page',
            $this->parameters
        );
    }
}
