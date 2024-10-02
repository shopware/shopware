<?php declare(strict_types=1);

namespace Shopware\Administration\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1726132532ExpandNotificationMessage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1726132532;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `notification` MODIFY `message` LONGTEXT;
        ');
    }
}
