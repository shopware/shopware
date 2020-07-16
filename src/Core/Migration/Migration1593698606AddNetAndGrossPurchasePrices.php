<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1593698606AddNetAndGrossPurchasePrices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1593698606;
    }

    public function update(Connection $connection): void
    {
        $this->migrateProductPurchasePriceField($connection);
        $this->migrateCartLineItemPurchasePriceRuleCondition($connection);
        $this->addSynchDatabaseTrigger($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function migrateCartLineItemPurchasePriceRuleCondition(Connection $connection): void
    {
        $rows = $connection->fetchAll('SELECT id, value FROM rule_condition WHERE type = "cartLineItemPurchasePrice"');
        foreach ($rows as $row) {
            $conditionValue = json_decode($row['value']);
            if (property_exists($conditionValue, 'isNet')) {
                continue;
            }

            $conditionValue->isNet = false;
            $connection->executeUpdate(
                'UPDATE rule_condition SET value = :conditionValue WHERE id = :id',
                [
                    'conditionValue' => json_encode($conditionValue),
                    'id' => $row['id'],
                ]
            );
        }
    }

    private function migrateProductPurchasePriceField(Connection $connection): void
    {
        // Add new 'purchase_prices' JSON field
        $connection->executeUpdate('ALTER TABLE `product` ADD `purchase_prices` JSON NULL AFTER `purchase_price`;');

        // Convert any existing purchase price values into the new 'purchase_prices' JSON field
        $defaultCurrencyId = Defaults::CURRENCY;
        $connection->executeUpdate(
            'UPDATE `product`
            LEFT JOIN tax ON product.tax_id = tax.id
            SET purchase_prices = IF(
                purchase_price IS NULL,
                NULL,
                JSON_OBJECT(
                    :currencyKey, JSON_OBJECT(
                        "net", `product`.`purchase_price` / (1 + (`tax`.`tax_rate` / 100)),
                        "gross", `product`.`purchase_price`,
                        "linked", "true",
                        "currencyId", :currencyId
                    )
                )
            );',
            [
                'currencyKey' => sprintf('c%s', $defaultCurrencyId),
                'currencyId' => $defaultCurrencyId,
            ]
        );
    }

    /**
     * Adds a database trigger that keeps the fields 'purchase_price' and 'purchase_prices' in sync. That means updating
     * either value will update the other.
     */
    private function addSynchDatabaseTrigger(Connection $connection): void
    {
        $query = sprintf(
            'CREATE TRIGGER product_purchase_prices_sync BEFORE UPDATE ON product
                FOR EACH ROW
                BEGIN
                IF NEW.purchase_prices != OLD.purchase_prices THEN BEGIN

                    SET NEW.purchase_price = JSON_EXTRACT(NEW.purchase_prices, \'$.c%1$s.gross\');

                END; ELSE BEGIN

                    IF NEW.purchase_price != OLD.purchase_price THEN BEGIN
                        DECLARE taxRate DECIMAL(10,2);
                        SET taxRate = (SELECT tax_rate FROM tax WHERE id = NEW.tax);

                        SET NEW.purchase_prices = CONCAT(
                            \'{"c%1$s": {"net": \',
                            CONVERT((NEW.purchase_price / (1 + (taxRate/100))), CHAR(50)),
                            \', "gross": \',
                            CONVERT(NEW.purchase_price, CHAR(50)),
                            \', "linked": true, "currencyId": "%1$s"}}\'
                        );
                    END; END IF;

                END; END IF;
                END',
            Defaults::CURRENCY
        );

        $this->createTrigger($connection, $query);
    }
}
