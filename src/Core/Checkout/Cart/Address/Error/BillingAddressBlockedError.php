<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class BillingAddressBlockedError extends Error implements AddressErrorInterface
{
    private const KEY = 'billing-address-blocked';

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - $addressId will be required and non-nullable
     */
    public function __construct(
        protected readonly string $name,
        protected readonly ?string $addressId = null,
    ) {
        if (!$addressId) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Not passing an $addressId is deprecated and will not be allowed in v6.8.0.0. Please provide a valid address ID.'
            );
        }

        $this->message = \sprintf(
            'Billings to billing address %s are not possible.',
            $name
        );

        parent::__construct($this->message);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getKey(): string
    {
        return \sprintf('%s-%s', self::KEY, $this->name);
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getId(): string
    {
        return $this->getKey();
    }

    public function getParameters(): array
    {
        return ['name' => $this->name];
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }
}
