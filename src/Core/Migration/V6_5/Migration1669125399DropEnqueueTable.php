<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1669125399DropEnqueueTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1669125399;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `enqueue`');
    }
}
