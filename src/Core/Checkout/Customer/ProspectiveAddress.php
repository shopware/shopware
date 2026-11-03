<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The parts of an address a visitor without a customer has entered, as far as rules, tax rules and shipping costs read them.
 *
 * @phpstan-type ProspectiveAddressPayload array{countryId: string, countryStateId: ?string, zipcode: ?string, city: ?string, street: ?string}
 */
#[Package('checkout')]
final class ProspectiveAddress extends Struct
{
    final public const FIELDS = ['countryId', 'countryStateId', 'zipcode', 'city', 'street'];

    /**
     * @internal
     */
    public function __construct(
        public readonly string $countryId,
        public readonly ?string $countryStateId = null,
        public readonly ?string $zipcode = null,
        public readonly ?string $city = null,
        public readonly ?string $street = null,
    ) {
    }

    /**
     * Reads an address of the context payload or the request, empty values become `null`.
     * Returns `null` without a country, as an address without a country does not locate the visitor.
     *
     * @internal
     */
    public static function fromPayload(mixed $address): ?self
    {
        if (!\is_array($address) || !\is_string($address['countryId'] ?? null) || $address['countryId'] === '') {
            return null;
        }

        $value = static fn (string $field): ?string => \is_string($address[$field] ?? null) && $address[$field] !== '' ? $address[$field] : null;

        return new self($address['countryId'], $value('countryStateId'), $value('zipcode'), $value('city'), $value('street'));
    }

    /**
     * Every field is set, even when it is empty, so that the stored address is replaced instead of merged.
     *
     * @internal
     *
     * @return ProspectiveAddressPayload
     */
    public function toPayload(): array
    {
        return [
            'countryId' => $this->countryId,
            'countryStateId' => $this->countryStateId,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'street' => $this->street,
        ];
    }

    public function getApiAlias(): string
    {
        return 'prospective_customer_address';
    }
}
