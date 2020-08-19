<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1597935198AddRefundHandlerIdentifier extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597935198;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `payment_method`
            ADD `refund_handler_identifier` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL AFTER `handler_identifier`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
