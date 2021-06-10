<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1594104496CashRounding extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594104496;
    }

    public function update(Connection $connection): void
    {
        $this->updateSchema($connection);
        $this->createCurrencyUpdateTrigger($connection);
        $this->createCurrencyInsertTrigger($connection);
        $this->createOrderInsertTrigger($connection);
        $this->migrateData($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateSchema(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `currency` CHANGE `decimal_precision` `decimal_precision` int NULL AFTER `position`;');
        $connection->executeUpdate('ALTER TABLE currency ADD COLUMN `item_rounding` JSON NULL;');
        $connection->executeUpdate('ALTER TABLE currency ADD COLUMN `total_rounding` JSON NULL;');

        $connection->executeUpdate('ALTER TABLE `order` ADD COLUMN `item_rounding` JSON NULL;');
        $connection->executeUpdate('ALTER TABLE `order` ADD COLUMN `total_rounding` JSON NULL;');

        $connection->executeUpdate('
CREATE TABLE IF NOT EXISTS `currency_country_rounding` (
  `id` binary(16) NOT NULL,
  `currency_id` binary(16) NOT NULL,
  `country_id` binary(16) NOT NULL,
  `item_rounding` json NOT NULL,
  `total_rounding` json NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) NULL,
  PRIMARY KEY (`id`),
  KEY `currency_id` (`currency_id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `currency_country_rounding_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `currency_country_rounding_ibfk_3` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    private function createCurrencyUpdateTrigger(Connection $connection): void
    {
        $query
            = 'CREATE TRIGGER currency_cash_rounding_update BEFORE UPDATE ON currency
            FOR EACH ROW
            BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN

                IF NEW.item_rounding IS NULL AND NEW.decimal_precision != OLD.decimal_precision AND NEW.decimal_precision IS NOT NULL THEN
                    SET NEW.item_rounding = JSON_OBJECT(
                        "decimals", NEW.decimal_precision,
                        "interval", 0.01,
                        "roundForNet", true
                    );

                    SET NEW.total_rounding = JSON_OBJECT(
                        "decimals", NEW.decimal_precision,
                        "interval", 0.01,
                        "roundForNet", true
                    );
                ## check if old value unchanged but new value written
                ELSEIF (NEW.decimal_precision = OLD.decimal_precision OR NEW.decimal_precision IS NULL AND NEW.item_rounding IS NOT NULL) THEN
                    SET NEW.decimal_precision = JSON_UNQUOTE(JSON_EXTRACT(NEW.item_rounding, "$.decimals")) + 0;
                END IF;
                END IF;
            END;';

        $this->createTrigger($connection, $query);
    }

    private function createCurrencyInsertTrigger(Connection $connection): void
    {
        $query
            = 'CREATE TRIGGER currency_cash_rounding_insert BEFORE INSERT ON currency
            FOR EACH ROW
            BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN

                IF NEW.decimal_precision IS NOT NULL AND NEW.item_rounding IS NULL THEN

                    SET NEW.item_rounding = JSON_OBJECT(
                        "decimals", NEW.decimal_precision,
                        "interval", 0.01,
                        "roundForNet", true
                    );

                    SET NEW.total_rounding = JSON_OBJECT(
                        "decimals", NEW.decimal_precision,
                        "interval", 0.01,
                        "roundForNet", true
                    );

                ELSEIF NEW.decimal_precision IS NULL AND NEW.item_rounding IS NOT NULL THEN

                    SET NEW.decimal_precision = JSON_UNQUOTE(JSON_EXTRACT(NEW.item_rounding, "$.decimals")) + 0;

                ELSEIF NEW.decimal_precision IS NOT NULL AND NEW.item_rounding IS NOT NULL THEN

                    SET NEW.decimal_precision = JSON_UNQUOTE(JSON_EXTRACT(NEW.item_rounding, "$.decimals")) + 0;

                END IF;
                END IF;
            END;';

        $this->createTrigger($connection, $query);
    }

    private function migrateData(Connection $connection): void
    {
        $currencies = $connection->fetchAll('SELECT id, decimal_precision FROM currency');

        foreach ($currencies as $currency) {
            $rounding = [
                'decimals' => $currency['decimal_precision'],
                'roundForNet' => true,
                'interval' => 0.01,
            ];

            $connection->executeUpdate(
                'UPDATE currency SET item_rounding = :rounding, total_rounding = :rounding WHERE id = :id',
                ['id' => $currency['id'], 'rounding' => json_encode($rounding)]
            );
        }

        $connection->executeUpdate('
            UPDATE `order`, `currency`
            SET `order`.item_rounding = currency.item_rounding
            WHERE `order`.currency_id = currency.id
        ');
    }

    private function createOrderInsertTrigger(Connection $connection): void
    {
        $query
            = 'CREATE TRIGGER order_cash_rounding_insert BEFORE INSERT ON `order`
            FOR EACH ROW
            BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF NEW.item_rounding IS NULL THEN
                        SET NEW.item_rounding = (SELECT item_rounding FROM currency WHERE id = NEW.currency_id);
                        SET NEW.total_rounding = NEW.item_rounding;
                    END IF;
                END IF;
            END;';

        $this->createTrigger($connection, $query);
    }
}
