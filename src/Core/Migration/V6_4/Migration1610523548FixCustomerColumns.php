<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610523548FixCustomerColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610523548;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'ALTER TABLE `customer`
             ADD COLUMN `double_opt_in_registration` TINYINT(1) NOT NULL DEFAULT 0 AFTER `doubleOptInRegistration`,
             ADD COLUMN `double_opt_in_email_sent_date` DATETIME(3) NULL AFTER `doubleOptInEmailSentDate`,
             ADD COLUMN `double_opt_in_confirm_date` DATETIME(3) NULL AFTER `doubleOptInConfirmDate`'
        );

        $connection->executeUpdate('
            UPDATE `customer`
            SET `customer`.`double_opt_in_registration` = `customer`.`doubleOptInRegistration`,
                `customer`.`double_opt_in_email_sent_date` = `customer`.`doubleOptInEmailSentDate`,
                `customer`.`double_opt_in_confirm_date` = `customer`.`doubleOptInConfirmDate`
        ');

        $this->addInsertTrigger($connection);
        $this->addUpdateTrigger($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeUpdate('DROP TRIGGER IF EXISTS customer_double_opt_in_insert;');
        $connection->executeUpdate('DROP TRIGGER IF EXISTS customer_double_opt_in_update;');

        $connection->executeUpdate(
            'ALTER TABLE `customer`
            DROP COLUMN `doubleOptInRegistration`,
            DROP COLUMN `doubleOptInEmailSentDate`,
            DROP COLUMN `doubleOptInConfirmDate`'
        );
    }

    private function addInsertTrigger(Connection $connection): void
    {
        $query = '
            CREATE TRIGGER customer_double_opt_in_insert BEFORE INSERT ON customer
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    SET NEW.doubleOptInRegistration = NEW.double_opt_in_registration;
                    SET NEW.doubleOptInEmailSentDate = NEW.double_opt_in_email_sent_date;
                    SET NEW.doubleOptInConfirmDate = NEW.double_opt_in_confirm_date;
                END IF;
            END;
            ';

        $this->createTrigger($connection, $query);
    }

    private function addUpdateTrigger(Connection $connection): void
    {
        $query = '
            CREATE TRIGGER customer_double_opt_in_update BEFORE UPDATE ON customer
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    SET NEW.doubleOptInRegistration = NEW.double_opt_in_registration;
                    SET NEW.doubleOptInEmailSentDate = NEW.double_opt_in_email_sent_date;
                    SET NEW.doubleOptInConfirmDate = NEW.double_opt_in_confirm_date;
                END IF;
            END;
            ';

        $this->createTrigger($connection, $query);
    }
}
