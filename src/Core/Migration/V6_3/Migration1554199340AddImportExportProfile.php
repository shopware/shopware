<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1554199340AddImportExportProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554199340;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE `import_export_profile` (
              `id` binary(16) NOT NULL,
              `name` varchar(255) NOT NULL,
              `system_default` tinyint(1) unsigned NOT NULL DEFAULT \'0\',
              `source_entity` varchar(255) NOT NULL,
              `file_type` varchar(255) NOT NULL,
              `delimiter` varchar(255),
              `enclosure` varchar(255),
              `mapping` LONGTEXT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->insert('import_export_profile', $this->getDefaultCustomerProfile($connection));
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getDefaultCustomerProfile(Connection $connection): array
    {
        $mapping = [];

        $fields = [
            'firstName',
            'lastName',
            'email',
            'customerNumber',
            'salesChannelId',
            'birthday',
            'salutationId',
            'salesChannelId',
            'defaultPaymentMethodId',
            'groupId',
            'guest',
        ];
        $addressFields = [
            'firstName',
            'lastName',
            'salutationId',
            'street',
            'zipcode',
            'city',
            'countryId',
        ];

        foreach (['defaultBillingAddress', 'defaultShippingAddress'] as $addressRef) {
            foreach ($addressFields as $addressField) {
                $fields[] = $addressRef . '.' . $addressField;
            }
        }

        foreach ($fields as $fieldName) {
            $mapping[] = [
                'fileField' => $fieldName,
                'entityField' => $fieldName,
                'valueSubstitutions' => $this->getValueSubstitutions($connection, $fieldName),
            ];
        }

        return [
            'id' => Uuid::randomBytes(),
            'name' => 'Default customer',
            'system_default' => 1,
            'source_entity' => 'customer',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => json_encode($mapping),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    private function getSalutationMap(Connection $connection): array
    {
        $result = [];
        foreach ($connection->fetchAll('SELECT * FROM salutation') as $row) {
            $result[$row['salutation_key']] = Uuid::fromBytesToHex($row['id']);
        }

        return $result;
    }

    private function getCustomerGroupMap(): array
    {
        return ['default' => 'cfbd5018d38d41d8adca10d94fc8bdd6'];
    }

    private function getCountryMap(Connection $connection): array
    {
        $result = [];
        foreach ($connection->fetchAll('SELECT * FROM country') as $row) {
            $result[$row['iso']] = Uuid::fromBytesToHex($row['id']);
        }

        return $result;
    }

    private function getSalesChannelMap(): array
    {
        return ['default' => '98432def39fc4624b33213a56b8c944d'];
    }

    private function getPaymentMethodMap(Connection $connection): array
    {
        $result = [];
        foreach ($connection->fetchAll('SELECT * FROM payment_method') as $row) {
            $key = mb_substr((string) mb_strrchr($row['handler_identifier'], '\\'), 1);
            $key = str_replace('payment', '', mb_strtolower($key));
            $result[$key] = Uuid::fromBytesToHex($row['id']);
        }

        return $result;
    }

    private function getValueSubstitutions(Connection $connection, string $propertyName): array
    {
        switch ($propertyName) {
            case 'groupId':
                return $this->getCustomerGroupMap();
            case 'defaultBillingAddress.salutationId':
            case 'defaultShippingAddress.salutationId':
            case 'salutationId':
                return $this->getSalutationMap($connection);
            case 'defaultPaymentMethodId':
                return $this->getPaymentMethodMap($connection);
            case 'salesChannelId':
                return $this->getSalesChannelMap();
            case 'defaultBillingAddress.countryId':
            case 'defaultShippingAddress.countryId':
                return $this->getCountryMap($connection);
            case 'guest':
                return $this->getBooleanMap();
        }

        return [];
    }

    private function getBooleanMap(): array
    {
        return ['0' => false, '1' => true];
    }
}
