<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1594886106AddDocumentBaseConfigSalesChannelPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594886106;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement('
                ALTER TABLE `document_base_config_sales_channel`
                ADD PRIMARY KEY (`id`);
            ');
        } catch (\Doctrine\DBAL\Exception $e) {
            // PK already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing
    }
}
