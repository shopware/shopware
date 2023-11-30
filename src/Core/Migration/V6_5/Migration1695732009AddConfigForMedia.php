<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('content')]
class Migration1695732009AddConfigForMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1695732009;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'media', 'config')) {
            return;
        }
        $connection->executeStatement('ALTER TABLE `media` ADD COLUMN `config` JSON NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
