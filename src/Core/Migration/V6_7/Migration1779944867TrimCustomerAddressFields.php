<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1779944867TrimCustomerAddressFields extends MigrationStep
{
    private const BATCH_SIZE = 1000;

    /**
     * @var list<string>
     */
    private const CUSTOMER_ADDRESS_FIELDS = [
        'first_name',
        'last_name',
        'street',
        'zipcode',
        'city',
        'company',
        'department',
        'title',
        'phone_number',
        'additional_address_line1',
        'additional_address_line2',
    ];

    public function getCreationTimestamp(): int
    {
        return 1779944867;
    }

    public function update(Connection $connection): void
    {
        $currentAddressId = null;
        $batchSize = self::BATCH_SIZE;

        while ($batchSize > 0) {
            $addresses = $this->fetchCustomerAddresses($connection, $currentAddressId);
            $batchSize = \count($addresses);

            foreach ($addresses as $address) {
                $currentAddressId = (string) $address['id'];
                $trimmedFields = $this->getTrimmedFields($address);

                if ($trimmedFields === []) {
                    continue;
                }

                $connection->update('customer_address', $trimmedFields, ['id' => $currentAddressId]);
            }
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function fetchCustomerAddresses(Connection $connection, ?string $lastId): array
    {
        $query = $connection->createQueryBuilder()
            ->select('id')
            ->from('customer_address')
            ->orderBy('id', 'ASC')
            ->setMaxResults(self::BATCH_SIZE);

        foreach (self::CUSTOMER_ADDRESS_FIELDS as $field) {
            $query->addSelect($field);
        }

        if ($lastId !== null) {
            $query->where('id > :lastId');
            $query->setParameter('lastId', $lastId);
        }

        /** @var list<array<string, string|null>> $addresses */
        $addresses = $query->executeQuery()->fetchAllAssociative();

        return $addresses;
    }

    /**
     * @param array<string, string|null> $address
     *
     * @return array<string, string>
     */
    private function getTrimmedFields(array $address): array
    {
        $trimmedFields = [];

        foreach (self::CUSTOMER_ADDRESS_FIELDS as $fieldKey) {
            $value = $address[$fieldKey] ?? null;

            if ($value === null) {
                continue;
            }

            $trimmedValue = mb_trim($value);

            if ($trimmedValue === $value) {
                continue;
            }

            $trimmedFields[$fieldKey] = $trimmedValue;
        }

        return $trimmedFields;
    }
}
