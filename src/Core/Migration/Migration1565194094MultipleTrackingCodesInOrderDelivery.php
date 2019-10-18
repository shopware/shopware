<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1565194094MultipleTrackingCodesInOrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565194094;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `order_delivery`
            ADD COLUMN `tracking_codes` JSON NOT NULL AFTER `shipping_method_id`,
            ADD CONSTRAINT `json.order_delivery.tracking_codes` CHECK (JSON_VALID(`tracking_codes`));
        ');
        $connection->executeQuery('
            UPDATE `order_delivery`
            SET `tracking_codes` = IF(`tracking_code` IS NULL OR `tracking_code` = "", JSON_ARRAY(), JSON_ARRAY(`tracking_code`));
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `order_delivery`
            DROP COLUMN `tracking_code`;
        ');
    }
}
