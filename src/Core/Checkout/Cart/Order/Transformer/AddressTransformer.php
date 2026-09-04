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
 *     firstName: string,
 *     lastName: string,
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
        $addressArray = array_filter([
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'salutationId' => $address->getSalutationId(),
            'title' => $address->getTitle(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
        ], static function (?string $value): bool {
            return $value !== null && $value !== '';
        });

        $addressArray['id'] = Uuid::randomHex();

        // required on the snapshot, so the keys survive even when a company account has no contact person
        $addressArray['firstName'] = $address->getFirstName();
        $addressArray['lastName'] = $address->getLastName();

        $customFields = $address->getCustomFields();
        if ($customFields !== null && $customFields !== []) {
            $addressArray['customFields'] = $customFields;
        }

        return $addressArray;
    }
}
