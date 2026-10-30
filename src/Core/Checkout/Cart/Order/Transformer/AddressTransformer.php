<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class AddressTransformer
{
    public static function transformCollection(CustomerAddressCollection $addresses, bool $useIdAsKey = false): array
    {
        $output = [];
        foreach ($addresses as $address) {
            if (\array_key_exists($address->getId(), $output)) {
                continue;
            }
            $output[$address->getId()] = self::transform($address);
        }

        if (!$useIdAsKey) {
            $output = array_values($output);
        }

        return $output;
    }

    public static function transform(CustomerAddressEntity $address): array
    {
        return array_filter([
            'id' => Uuid::randomHex(),
            ...self::getFields($address),
            'customFields' => $address->getCustomFields(),
        ]);
    }

    /**
     * Use this instead of `transform()` to overwrite an existing order address.
     * Empty fields are `null` instead of left out, so the order address doesn't keep their old values.
     * Empty custom fields are still left out, as extensions often write them on the order address and the DAL merges them on update.
     *
     * @return array<string, mixed>
     */
    public static function transformForUpdate(CustomerAddressEntity $address): array
    {
        $emptyFields = array_fill_keys(array_keys(self::getFields($address)), null);

        return [...$emptyFields, ...self::transform($address)];
    }

    /**
     * @return array{
     *     company: string|null,
     *     department: string|null,
     *     salutationId: string|null,
     *     title: string|null,
     *     firstName: string,
     *     lastName: string,
     *     street: string,
     *     zipcode: string|null,
     *     city: string,
     *     phoneNumber: string|null,
     *     additionalAddressLine1: string|null,
     *     additionalAddressLine2: string|null,
     *     countryId: string,
     *     countryStateId: string|null
     * }
     */
    private static function getFields(CustomerAddressEntity $address): array
    {
        return [
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'salutationId' => $address->getSalutationId(),
            'title' => $address->getTitle(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
        ];
    }
}
