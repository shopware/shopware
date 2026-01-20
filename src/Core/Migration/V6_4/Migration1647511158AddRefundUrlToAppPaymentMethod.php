<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1647511158AddRefundUrlToAppPaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1647511158;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'app_payment_method', 'refund_url')) {
            $connection->executeStatement('ALTER TABLE `app_payment_method` ADD COLUMN `refund_url` VARCHAR(255) NULL AFTER `capture_url`');
        }
    }
}
