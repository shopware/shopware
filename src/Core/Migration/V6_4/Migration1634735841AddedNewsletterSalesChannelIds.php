<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1634735841AddedNewsletterSalesChannelIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1634735841;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `customer` ADD `newsletter_sales_channel_ids` json NULL AFTER `last_login`');
        $this->registerIndexer($connection, 'newsletter_recipient.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
