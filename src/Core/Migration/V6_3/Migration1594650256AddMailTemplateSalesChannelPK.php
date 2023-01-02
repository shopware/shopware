<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Shopware\Core\Framework\Log\Package;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 * @package core
 */
#[Package('core')]
class Migration1594650256AddMailTemplateSalesChannelPK extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1594650256;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement('
                ALTER TABLE `mail_template_sales_channel`
                ADD PRIMARY KEY (`id`);
            ');
        } catch (Exception $e) {
            // PK already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing
    }
}
