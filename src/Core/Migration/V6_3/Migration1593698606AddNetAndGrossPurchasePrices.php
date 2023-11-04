<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
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
        $this->addUpdateDatabaseTrigger($connection);
        $this->addInsertDatabaseTrigger($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function migrateCartLineItemPurchasePriceRuleCondition(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative('SELECT id, value FROM rule_condition WHERE type = "cartLineItemPurchasePrice"');
        foreach ($rows as $row) {
            $conditionValue = json_decode((string) $row['value'], true, 512, \JSON_THROW_ON_ERROR);
            if (\array_key_exists('isNet', $conditionValue)) {
                continue;
            }

            $conditionValue['isNet'] = false;
            $connection->executeStatement(
                'UPDATE rule_condition SET value = :conditionValue WHERE id = :id',
                [
                    'conditionValue' => json_encode($conditionValue, \JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ]
            );
        }
    }

    private function migrateProductPurchasePriceField(Connection $connection): void
    {
        // Add new 'purchase_prices' JSON field
        $connection->executeStatement('ALTER TABLE `product` ADD `purchase_prices` JSON NULL AFTER `purchase_price`;');

        // Convert any existing purchase price values into the new 'purchase_prices' JSON field
        $defaultCurrencyId = Defaults::CURRENCY;
        $connection->executeStatement(
            'UPDATE `product`
            LEFT JOIN tax ON product.tax = tax.id
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
    private function addUpdateDatabaseTrigger(Connection $connection): void
    {
        $query = sprintf(
            'CREATE TRIGGER product_purchase_prices_update BEFORE UPDATE ON product
                FOR EACH ROW BEGIN
                    IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN BEGIN
                        IF (NEW.purchase_prices != OLD.purchase_prices) OR (NEW.purchase_prices IS NOT NULL AND OLD.purchase_prices IS NULL) THEN BEGIN

                            SET NEW.purchase_price = JSON_UNQUOTE(JSON_EXTRACT(
                                    NEW.purchase_prices,
                                    CONCAT("$.", JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(NEW.purchase_prices), "$[0]")), ".gross")
                                )) + 0.0;

                        END; ELSE BEGIN

                            IF (NEW.purchase_price != OLD.purchase_price) OR (NEW.purchase_price IS NOT NULL AND NEW.purchase_prices IS NULL) THEN BEGIN
                                DECLARE taxRate DECIMAL(10,2);
                                IF NEW.tax_id IS NOT NULL THEN BEGIN
                                    SET taxRate = (SELECT tax_rate FROM tax WHERE id = NEW.tax_id);
                                END; ELSE BEGIN
                                    SET taxRate = (SELECT tax_rate FROM tax WHERE id = OLD.tax_id);
                                END; END IF;

                                SET NEW.purchase_prices = CONCAT(
                                    \'{"c%1$s": {"net": \',
                                    CONVERT((NEW.purchase_price / (1 + (taxRate/100))), CHAR(50)),
                                    \', "gross": \',
                                    CONVERT(NEW.purchase_price, CHAR(50)),
                                    \', "linked": true, "currencyId": "%1$s"}}\'
                                );
                            END; END IF;

                        END; END IF;
                    END; END IF;
                END',
            Defaults::CURRENCY
        );

        $this->createTrigger($connection, $query);
    }

    /**
     * Adds a database trigger that keeps the fields 'purchase_price' and 'purchase_prices' in sync. That means insert
     * either value will update the other.
     */
    private function addInsertDatabaseTrigger(Connection $connection): void
    {
        $query = sprintf(
            'CREATE TRIGGER product_purchase_prices_insert BEFORE INSERT ON product
                FOR EACH ROW BEGIN
                    IF @TRIGGER_DISABLED IS NULL OR @TRIGGER_DISABLED = 0 THEN BEGIN
                        IF NEW.purchase_prices IS NOT NULL THEN BEGIN

                            SET NEW.purchase_price = JSON_UNQUOTE(JSON_EXTRACT(
                                    NEW.purchase_prices,
                                    CONCAT("$.", JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(NEW.purchase_prices), "$[0]")), ".gross")
                                )) + 0.0;

                        END; ELSE BEGIN

                            IF NEW.purchase_price IS NOT NULL THEN BEGIN
                                DECLARE taxRate DECIMAL(10,2);
                                SET taxRate = (SELECT tax_rate FROM tax WHERE id = NEW.tax_id);

                                SET NEW.purchase_prices = CONCAT(
                                    \'{"c%1$s": {"net": \',
                                    CONVERT((NEW.purchase_price / (1 + (taxRate/100))), CHAR(50)),
                                    \', "gross": \',
                                    CONVERT(NEW.purchase_price, CHAR(50)),
                                    \', "linked": true, "currencyId": "%1$s"}}\'
                                );
                            END; END IF;

                        END; END IF;
                    END; END IF;
                END',
            Defaults::CURRENCY
        );

        $this->createTrigger($connection, $query);
    }
}
