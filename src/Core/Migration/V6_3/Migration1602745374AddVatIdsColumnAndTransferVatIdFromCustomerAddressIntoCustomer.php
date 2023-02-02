<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1602745374AddVatIdsColumnAndTransferVatIdFromCustomerAddressIntoCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1602745374;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer`
            ADD COLUMN `vat_ids` JSON NULL DEFAULT NULL AFTER `title`;
        ');

        $this->addInsertTrigger($connection);
        $this->addUpdateTrigger($connection);

        $connection->executeStatement('
            UPDATE `customer`, `customer_address`
            SET `customer`.`vat_ids` = JSON_ARRAY(`customer_address`.`vat_id`)
            WHERE `customer`.`default_billing_address_id` = `customer_address`.`id` AND `customer_address`.`vat_id` IS NOT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * Adds a database trigger that keeps the fields 'vat_ids' in `customer` and 'vat_id' in `customer_address` in sync.
     * That means inserting either value will update the other.
     */
    private function addInsertTrigger(Connection $connection): void
    {
        $query = 'CREATE TRIGGER customer_address_vat_id_insert AFTER INSERT ON customer_address
            FOR EACH ROW BEGIN
                IF NEW.vat_id IS NOT NULL THEN
                    UPDATE customer SET vat_ids = JSON_ARRAY(NEW.vat_id)
                        WHERE customer.default_billing_address_id = NEW.id
                        AND (JSON_CONTAINS(vat_ids, \'"$NEW.vat_id"\') = 0 OR vat_ids IS NULL);
                END IF;
            END;';
        $this->createTrigger($connection, $query);
    }

    /**
     * Adds a database trigger that keeps the fields 'vat_ids' in `customer` and 'vat_id' in `customer_address` in sync.
     * That means updating either value will update the other.
     */
    private function addUpdateTrigger(Connection $connection): void
    {
        $query = 'CREATE TRIGGER customer_address_vat_id_update AFTER UPDATE ON customer_address
            FOR EACH ROW BEGIN
                IF (OLD.vat_id IS NOT NULL AND NEW.vat_id IS NULL) THEN
                    UPDATE customer SET vat_ids = JSON_REMOVE(vat_ids, JSON_UNQUOTE(JSON_SEARCH(vat_ids, \'one\', OLD.vat_id)))
                        WHERE customer.default_billing_address_id = NEW.id
                        AND JSON_SEARCH(vat_ids, \'one\', OLD.vat_id) IS NOT NULL;
                ELSEIF (OLD.vat_id IS NULL AND NEW.vat_id IS NOT NULL) OR (OLD.vat_id <> NEW.vat_id) THEN
                    UPDATE customer SET vat_ids = JSON_ARRAY(NEW.vat_id)
                        WHERE customer.default_billing_address_id = NEW.id
                        AND (JSON_CONTAINS(vat_ids, \'"$NEW.vat_id"\') = 0 OR vat_ids IS NULL);
                END IF;
            END;';
        $this->createTrigger($connection, $query);
    }
}
