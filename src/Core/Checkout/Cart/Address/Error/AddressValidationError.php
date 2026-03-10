<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('checkout')]
class AddressValidationError extends Error implements AddressErrorInterface
{
    private const KEY = 'address-invalid';

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $addressId will be required and non-nullable
     */
    public function __construct(
        protected readonly bool $isBillingAddress,
        protected readonly ConstraintViolationList $violations,
        protected readonly ?string $addressId = null,
    ) {
        if (!$addressId) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Not passing an $addressId is deprecated and will not be allowed in v6.8.0.0. Please provide a valid address ID.'
            );
        }

        $this->message = \sprintf(
            'Please check your %s address for missing or invalid values.',
            $isBillingAddress ? 'billing' : 'shipping'
        );

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return $this->getMessageKey();
    }

    public function getMessageKey(): string
    {
        return \sprintf('%s-%s', $this->isBillingAddress ? 'billing' : 'shipping', self::KEY);
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return ['isBillingAddress' => $this->isBillingAddress, 'violations' => $this->violations];
    }

    public function isBillingAddress(): bool
    {
        return $this->isBillingAddress;
    }

    public function getViolations(): ConstraintViolationList
    {
        return $this->violations;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }
}
