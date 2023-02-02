<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1587109484AddAfterOrderPaymentFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1587109484;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'ALTER TABLE payment_method
            ADD COLUMN `after_order_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`'
        );

        $connection->executeUpdate(
            'UPDATE `payment_method`
            SET `after_order_enabled` = 1 WHERE `handler_identifier` IN (
                "Shopware\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\DebitPayment",
                "Shopware\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\CashPayment",
                "Shopware\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\PrePayment",
                "Shopware\\\Core\\\Checkout\\\Payment\\\Cart\\\PaymentHandler\\\InvoicePayment"
            )'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
