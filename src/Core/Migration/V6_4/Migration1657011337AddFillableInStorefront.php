<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1657011337AddFillableInStorefront extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1657011337;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'custom_field', 'allow_customer_write')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `custom_field` ADD `allow_customer_write` tinyint default 0 NOT NULL');
    }
}
