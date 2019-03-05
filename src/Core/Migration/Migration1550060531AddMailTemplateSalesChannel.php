<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550060531AddMailTemplateSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550060531;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `mail_template_sales_channel` (
                `mail_template_id` binary(16) NOT NULL,
                `sales_channel_id` binary(16) NOT NULL,
                `created_at` datetime(3) NOT NULL,
                CONSTRAINT `fk.mail_template_sales_channel.mail_template_id` FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.mail_template_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
