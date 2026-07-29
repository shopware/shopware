<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
trait CustomerAddressDataNormalizerTrait
{
    /**
     * @var list<string>
     */
    private const ADDRESS_FIELDS_TO_TRIM = [
        'title',
        'firstName',
        'lastName',
        'street',
        'zipcode',
        'city',
        'company',
        'department',
        'phoneNumber',
        'additionalAddressLine1',
        'additionalAddressLine2',
    ];

    /**
     * @param array<string, mixed> $addressData
     *
     * @return array<string, mixed>
     */
    private function trimAddressFields(array $addressData): array
    {
        foreach (self::ADDRESS_FIELDS_TO_TRIM as $field) {
            if (!\is_string($addressData[$field] ?? null)) {
                continue;
            }

            $addressData[$field] = mb_trim($addressData[$field]);
        }

        return $addressData;
    }
}
