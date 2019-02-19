<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550220874AddMailHeaderFooterSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550220874;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `sales_channel`
              ADD COLUMN `mail_header_footer_id` BINARY(16) NULL,
              ADD CONSTRAINT `fk.sales_channel.id` FOREIGN KEY (`mail_header_footer_id`)
              REFERENCES `mail_header_footer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
