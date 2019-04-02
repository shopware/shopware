<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552464237SalutationDefaultsCustomerUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552464237;
    }

    public function update(Connection $connection): void
    {
        $salutationId = (string) $connection->fetchColumn('SELECT id FROM salutation');
        $this->updateCustomerToUseSalutations($connection, $salutationId);
        $this->updateCustomerAddressToUseSalutations($connection, $salutationId);
        $this->updateOrderCustomerToUseSalutations($connection, $salutationId);
        $this->updateOrderAddressToUseSalutations($connection, $salutationId);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateCustomerToUseSalutations(Connection $connection, string $salutationId): void
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
            SET `salutation_id` = :id
        ', [':id' => $salutationId]);

        $connection->executeQuery('
            ALTER TABLE `customer`
            CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
        ');
    }

    private function updateCustomerAddressToUseSalutations(Connection $connection, string $salutationId): void
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
                SET `salutation_id` = :id
            ', [':id' => $salutationId]);

        $connection->executeQuery('
                ALTER TABLE `customer_address`
                CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
            ');
    }

    private function updateOrderCustomerToUseSalutations(Connection $connection, string $salutationId): void
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
                SET `salutation_id` = :id
            ', [':id' => $salutationId]);

        $connection->executeQuery('
                ALTER TABLE `order_customer`
                CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
            ');
    }

    private function updateOrderAddressToUseSalutations(Connection $connection, string $salutationId): void
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
                SET `salutation_id` = :id
            ', [':id' => $salutationId]);

        $connection->executeQuery('
                ALTER TABLE `order_address`
                CHANGE `salutation_id` `salutation_id` BINARY(16) NOT NULL;
            ');
    }
}
