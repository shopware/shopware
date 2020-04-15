<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1586940040GoogleShoppingMerchantAccount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586940040;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `google_shopping_merchant_account` ADD `datafeed_id` VARCHAR(255)  COLLATE utf8mb4_unicode_ci DEFAULT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
