<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * What is known about a visitor who has no customer yet, e.g. the address typed into a headless checkout form.
 * It only exists on a sales channel context without a customer and is never persisted as a customer.
 *
 * @phpstan-type ProspectiveAddress array{countryId: string, countryStateId: ?string, zipcode: ?string, city: ?string, street: ?string}
 */
#[Package('checkout')]
final class ProspectiveCustomer extends Struct
{
    final public const ADDRESS_FIELDS = ['countryId', 'countryStateId', 'zipcode', 'city', 'street'];

    /**
     * @internal
     *
     * @param ProspectiveAddress $shippingAddress
     * @param ProspectiveAddress|null $billingAddress
     */
    public function __construct(
        protected array $shippingAddress,
        protected ?array $billingAddress,
        private readonly CustomerEntity $ruleCustomer,
    ) {
    }

    /**
     * @return ProspectiveAddress
     */
    public function getShippingAddress(): array
    {
        return $this->shippingAddress;
    }

    /**
     * @return ProspectiveAddress|null null when the billing address is the shipping address
     */
    public function getBillingAddress(): ?array
    {
        return $this->billingAddress;
    }

    /**
     * @internal
     *
     * @return ProspectiveAddress|null the address with every field set, empty values as null
     */
    public static function normalizeAddress(mixed $address): ?array
    {
        if (!\is_array($address) || !\is_string($address['countryId'] ?? null) || $address['countryId'] === '') {
            return null;
        }

        $value = static fn (string $field): ?string => \is_string($address[$field] ?? null) && $address[$field] !== '' ? $address[$field] : null;

        return [
            'countryId' => $address['countryId'],
            'countryStateId' => $value('countryStateId'),
            'zipcode' => $value('zipcode'),
            'city' => $value('city'),
            'street' => $value('street'),
        ];
    }

    /**
     * @internal only rule matching may see this customer, it does not exist in the database
     */
    public function getRuleCustomer(): CustomerEntity
    {
        return $this->ruleCustomer;
    }

    public function jsonSerialize(): array
    {
        $vars = parent::jsonSerialize();
        unset($vars['ruleCustomer']);

        return $vars;
    }

    public function getApiAlias(): string
    {
        return 'prospective_customer';
    }
}
