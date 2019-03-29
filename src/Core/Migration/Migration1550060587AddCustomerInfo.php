<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1550060587AddCustomerInfo extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550060587;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `customer`
            ADD `language_id` BINARY(16)                              NULL DEFAULT :fallbackLanguage AFTER `sales_channel_id`,
            ADD `company`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL                           AFTER `last_name`;
        ', ['fallbackLanguage' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `customer`
            CHANGE `language_id` `language_id` BINARY(16) NOT NULL AFTER `sales_channel_id`;
        ');
    }
}
