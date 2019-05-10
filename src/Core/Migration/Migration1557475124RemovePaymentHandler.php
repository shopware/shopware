<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1557475124RemovePaymentHandler extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1557475124;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate("
            UPDATE `payment_method` SET`handler_identifier` = 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\DefaultPayment' 
            
            WHERE 
            handler_identifier LIKE '%InvoicePayment' OR 
            handler_identifier LIKE '%CashPayment' OR 
            handler_identifier LIKE '%PrePayment' OR 
            handler_identifier LIKE '%DebitPayment'
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
