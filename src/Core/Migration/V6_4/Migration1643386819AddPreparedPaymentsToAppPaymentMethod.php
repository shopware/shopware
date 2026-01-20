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
class Migration1643386819AddPreparedPaymentsToAppPaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1643386819;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'app_payment_method', 'validate_url')) {
            $connection->executeStatement('ALTER TABLE `app_payment_method` ADD COLUMN `validate_url` VARCHAR(255) NULL AFTER `finalize_url`');
        }

        if (!TableHelper::columnExists($connection, 'app_payment_method', 'capture_url')) {
            $connection->executeStatement('ALTER TABLE `app_payment_method` ADD COLUMN `capture_url` VARCHAR(255) NULL AFTER `validate_url`');
        }
    }
}
