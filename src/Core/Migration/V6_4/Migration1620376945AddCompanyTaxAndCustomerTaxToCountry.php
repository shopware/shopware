<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1620376945AddCompanyTaxAndCustomerTaxToCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620376945;
    }

    public function update(Connection $connection): void
    {
        $this->addCompanyTaxAndCustomerTaxColumns($connection);
        $this->addInsertTrigger($connection);
        $this->addUpdateTrigger($connection);
        $this->migrateDataFromTaxFreeAndCompanyTaxFreeToNewFields($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function addCompanyTaxAndCustomerTaxColumns(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `country`
            ADD COLUMN `customer_tax` JSON NULL AFTER `vat_id_required`,
            ADD COLUMN `company_tax` JSON NULL AFTER `customer_tax`;'
        );
    }

    private function migrateDataFromTaxFreeAndCompanyTaxFreeToNewFields(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `country` SET
            `customer_tax` = JSON_OBJECT("enabled", `tax_free`, "currencyId", :currencyId, "amount", 0),
            `company_tax` = JSON_OBJECT("enabled", `company_tax_free`, "currencyId", :currencyId, "amount", 0);',
            ['currencyId' => Defaults::CURRENCY]
        );
    }

    private function addInsertTrigger(Connection $connection): void
    {
        $query = 'CREATE TRIGGER country_tax_free_insert BEFORE INSERT ON country
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF NEW.tax_free = 1 OR NEW.customer_tax IS NULL THEN
                        SET NEW.customer_tax = JSON_OBJECT("enabled", NEW.tax_free, "currencyId", :currencyId, "amount", 0);
                    ELSEIF NEW.customer_tax IS NOT NULL THEN
                        SET NEW.tax_free = JSON_EXTRACT(NEW.customer_tax, "$.enabled");
                    END IF;
                    IF NEW.company_tax_free = 1 OR NEW.company_tax IS NULL THEN
                        SET NEW.company_tax = JSON_OBJECT("enabled", NEW.company_tax_free, "currencyId", :currencyId, "amount", 0);
                    ELSEIF NEW.company_tax IS NOT NULL THEN
                        SET NEW.company_tax_free = JSON_EXTRACT(NEW.company_tax, "$.enabled");
                    END IF;
                END IF;
            END;';
        $this->createTrigger($connection, $query, ['currencyId' => Defaults::CURRENCY]);
    }

    private function addUpdateTrigger(Connection $connection): void
    {
        $query = 'CREATE TRIGGER country_tax_free_update BEFORE UPDATE ON country
            FOR EACH ROW BEGIN
                IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN
                    IF NEW.tax_free <> OLD.tax_free THEN
                        SET NEW.customer_tax = JSON_OBJECT("enabled", NEW.tax_free, "currencyId", JSON_EXTRACT(OLD.customer_tax, "$.currencyId"), "amount", JSON_EXTRACT(OLD.customer_tax, "$.amount"));
                    ELSEIF NEW.tax_free = OLD.tax_free AND JSON_EXTRACT(NEW.customer_tax, "$.enabled") <> JSON_EXTRACT(OLD.customer_tax, "$.enabled") THEN
                        SET NEW.tax_free = JSON_EXTRACT(NEW.customer_tax, "$.enabled");
                    END IF;
                    IF NEW.company_tax_free <> OLD.company_tax_free THEN
                        SET NEW.company_tax = JSON_OBJECT("enabled", NEW.company_tax_free, "currencyId", JSON_EXTRACT(OLD.company_tax, "$.currencyId"), "amount", JSON_EXTRACT(OLD.company_tax, "$.amount"));
                    ELSEIF NEW.company_tax_free = OLD.company_tax_free AND JSON_EXTRACT(NEW.company_tax, "$.enabled") <> JSON_EXTRACT(OLD.company_tax, "$.enabled") THEN
                        SET NEW.company_tax_free = JSON_EXTRACT(NEW.company_tax, "$.enabled");
                    END IF;
                END IF;
            END;';
        $this->createTrigger($connection, $query);
    }
}
