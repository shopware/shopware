<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type TransformedAddressArray array{
 *     id: non-empty-string,
 *     company?: non-empty-string,
 *     department?: non-empty-string,
 *     salutationId?: non-empty-string,
 *     title?: non-empty-string,
 *     firstName?: non-empty-string,
 *     lastName?: non-empty-string,
 *     street?: non-empty-string,
 *     zipcode?: non-empty-string,
 *     city?: non-empty-string,
 *     phoneNumber?: non-empty-string,
 *     additionalAddressLine1?: non-empty-string,
 *     additionalAddressLine2?: non-empty-string,
 *     countryId?: non-empty-string,
 *     countryStateId?: non-empty-string,
 *     customFields?: array<string, mixed>
 * }
 * @phpstan-type TransformedAddressUpdateArray array{
 *     id: non-empty-string,
 *     company: non-empty-string|null,
 *     department: non-empty-string|null,
 *     salutationId: non-empty-string|null,
 *     title: non-empty-string|null,
 *     firstName: non-empty-string|null,
 *     lastName: non-empty-string|null,
 *     street: non-empty-string|null,
 *     zipcode: non-empty-string|null,
 *     city: non-empty-string|null,
 *     phoneNumber: non-empty-string|null,
 *     additionalAddressLine1: non-empty-string|null,
 *     additionalAddressLine2: non-empty-string|null,
 *     countryId: non-empty-string|null,
 *     countryStateId: non-empty-string|null,
 *     customFields?: array<string, mixed>
 * }
 */
#[Package('checkout')]
class AddressTransformer
{
    /**
     * @return ($useIdAsKey is false ? list<TransformedAddressArray>: array<string, TransformedAddressArray>)
     */
    public static function transformCollection(CustomerAddressCollection $addresses, bool $useIdAsKey = false): array
    {
        $output = [];
        foreach ($addresses as $address) {
            if (\array_key_exists($address->getId(), $output)) {
                continue;
            }
            $output[$address->getId()] = self::transform($address);
        }

        if ($useIdAsKey === false) {
            return array_values($output);
        }

        return $output;
    }

    /**
     * @return TransformedAddressArray
     */
    public static function transform(CustomerAddressEntity $address): array
    {
        $addressArray = array_filter(self::getFields($address), static function (?string $value): bool {
            return $value !== null && $value !== '';
        });

        $addressArray['id'] = Uuid::randomHex();

        $customFields = $address->getCustomFields();
        if ($customFields !== null && $customFields !== []) {
            $addressArray['customFields'] = $customFields;
        }

        return $addressArray;
    }

    /**
     * Use this instead of `transform()` to overwrite an existing order address.
     * Empty fields are `null` instead of left out, so the order address doesn't keep their old values.
     * Empty custom fields are still left out, as extensions often write them on the order address and the DAL merges them on update.
     *
     * @return TransformedAddressUpdateArray
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
