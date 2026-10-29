<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;

/**
 * Dispatched under the name of the address definition, for example `framework.validation.address.create`.
 * `getData()` holds the whole input, for example the registration data; `getAddress()` holds the address the definition is validated against.
 */
#[Package('checkout')]
final class BuildAddressValidationEvent extends BuildValidationEvent
{
    public function __construct(
        DataValidationDefinition $definition,
        DataBag $data,
        Context $context,
        private readonly DataBag $address,
        private readonly ?AddressType $addressType = null,
    ) {
        parent::__construct($definition, $data, $context);
    }

    public function getAddress(): DataBag
    {
        return $this->address;
    }

    /**
     * Null when the address is not validated as a billing or shipping address, for example in the address book.
     */
    public function getAddressType(): ?AddressType
    {
        return $this->addressType;
    }
}
