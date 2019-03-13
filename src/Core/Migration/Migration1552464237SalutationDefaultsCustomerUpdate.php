<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1552464237SalutationDefaultsCustomerUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552464237;
    }

    public function update(Connection $connection): void
    {
        $this->clearSalutations($connection);
        $this->createSalutations($connection);
        $this->updateCustomerToUseSalutations($connection);
        $this->updateCustomerAddressToUseSalutations($connection);
        $this->updateOrderCustomerToUseSalutations($connection);
        $this->updateOrderAddressToUseSalutations($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function clearSalutations(Connection $connection): void
    {
        $connection->executeQuery('
            DELETE FROM `salutation`
            WHERE `salutation_key` IN (:mr, :mrs, :miss, :diverse);
        ', [
            ':mr' => Defaults::SALUTATION_KEY_MR,
            ':mrs' => Defaults::SALUTATION_KEY_MRS,
            ':miss' => Defaults::SALUTATION_KEY_MISS,
            ':diverse' => 'divers',
        ]);
    }

    private function createSalutations(Connection $connection): void
    {
        $mr = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MR);
        $mrs = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MRS);
        $miss = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MISS);
        $diverse = Uuid::fromHexToBytes(Defaults::SALUTATION_ID_DIVERSE);

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // Inserts for: Mr.
        $connection->insert('salutation', [
            'id' => $mr,
            'salutation_key' => Defaults::SALUTATION_KEY_MR,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageEn,
            'name' => 'Mr.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageDe,
            'name' => 'Herr',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Mrs.
        $connection->insert('salutation', [
            'id' => $mrs,
            'salutation_key' => Defaults::SALUTATION_KEY_MRS,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageEn,
            'name' => 'Mrs.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageDe,
            'name' => 'Frau',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Miss
        $connection->insert('salutation', [
            'id' => $miss,
            'salutation_key' => Defaults::SALUTATION_KEY_MISS,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageEn,
            'name' => 'Miss',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageDe,
            'name' => 'Fräulein',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Diverse
        $connection->insert('salutation', [
            'id' => $diverse,
            'salutation_key' => Defaults::SALUTATION_KEY_DIVERSE,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $diverse,
            'language_id' => $languageEn,
            'name' => 'Mx.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $diverse,
            'language_id' => $languageDe,
            'name' => 'Divers',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }

    private function updateCustomerToUseSalutations(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `customer`
            DROP `salutation`;
        ');

        $connection->executeQuery('
            ALTER TABLE `customer`
            ADD `salutation_id` BINARY(16) NULL AFTER `customer_number`,
            ADD CONSTRAINT `fk.customer.salutation_id`
              FOREIGN KEY (`salutation_id`)
              REFERENCES `salutation` (`id`)
              ON DELETE RESTRICT
              ON UPDATE CASCADE
        ');

        $connection->executeQuery('
            UPDATE `customer`
            SET `salutation_id` = :mr
        ', [':mr' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MR)]);

        $connection->executeQuery('
            ALTER TABLE `customer`
            CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
        ');
    }

    private function updateCustomerAddressToUseSalutations(Connection $connection): void
    {
        $connection->executeQuery('
                ALTER TABLE `customer_address`
                DROP `salutation`;
            ');

        $connection->executeQuery('
                ALTER TABLE `customer_address`
                ADD `salutation_id` BINARY(16) NULL AFTER `department`,
                ADD CONSTRAINT `fk.customer_address.salutation_id`
                  FOREIGN KEY (`salutation_id`)
                  REFERENCES `salutation` (`id`)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE
            ');

        $connection->executeQuery('
                UPDATE `customer_address`
                SET `salutation_id` = :mr
            ', [':mr' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MR)]);

        $connection->executeQuery('
                ALTER TABLE `customer_address`
                CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
            ');
    }

    private function updateOrderCustomerToUseSalutations(Connection $connection): void
    {
        $connection->executeQuery('
                ALTER TABLE `order_customer`
                DROP `salutation`;
            ');

        $connection->executeQuery('
                ALTER TABLE `order_customer`
                ADD `salutation_id` BINARY(16) NULL AFTER `email`,
                ADD CONSTRAINT `fk.order_customer.salutation_id`
                  FOREIGN KEY (`salutation_id`)
                  REFERENCES `salutation` (`id`)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE
            ');

        $connection->executeQuery('
                UPDATE `order_customer`
                SET `salutation_id` = :mr
            ', [':mr' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MR)]);

        $connection->executeQuery('
                ALTER TABLE `order_customer`
                CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
            ');
    }

    private function updateOrderAddressToUseSalutations(Connection $connection): void
    {
        $connection->executeQuery('
                ALTER TABLE `order_address`
                DROP `salutation`;
            ');

        $connection->executeQuery('
                ALTER TABLE `order_address`
                ADD `salutation_id` BINARY(16) NULL AFTER `department`,
                ADD CONSTRAINT `fk.order_address.salutation_id`
                  FOREIGN KEY (`salutation_id`)
                  REFERENCES `salutation` (`id`)
                  ON DELETE RESTRICT
                  ON UPDATE CASCADE
            ');

        $connection->executeQuery('
                UPDATE `order_address`
                SET `salutation_id` = :mr
            ', [':mr' => Uuid::fromHexToBytes(Defaults::SALUTATION_ID_MR)]);

        $connection->executeQuery('
                ALTER TABLE `order_address`
                CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
            ');
    }
}
