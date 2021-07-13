<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1612184092AddUrlLandingPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612184092;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `landing_page_translation`
            ADD COLUMN `url` varchar(255) NULL AFTER `name`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
