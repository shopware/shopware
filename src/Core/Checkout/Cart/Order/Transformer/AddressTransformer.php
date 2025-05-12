<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Transformer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type TransformedAddressArray array{id: non-falsy-string, company?: non-falsy-string, department?: non-falsy-string, salutationId?: non-falsy-string, title?: non-falsy-string, firstName?: non-falsy-string, lastName?: non-falsy-string, street?: non-falsy-string, zipcode?: non-falsy-string, city?: non-falsy-string, phoneNumber?: non-falsy-string, additionalAddressLine1?: non-falsy-string, additionalAddressLine2?: non-falsy-string, countryId?: non-falsy-string, countryStateId?: non-falsy-string, customFields?: array<string, mixed>}
 */
#[Package('checkout')]
class AddressTransformer
{
    /**
     * @return list<TransformedAddressArray>|array<string, TransformedAddressArray>
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

        if (!$useIdAsKey) {
            return array_values($output);
        }

        return $output;
    }

    /**
     * @return TransformedAddressArray
     */
    public static function transform(CustomerAddressEntity $address): array
    {
        return array_filter([
            'id' => Uuid::randomHex(),
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
            'customFields' => $address->getCustomFields(),
        ]);
    }
}
