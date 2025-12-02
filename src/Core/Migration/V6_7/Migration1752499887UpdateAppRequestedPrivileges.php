<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752499887UpdateAppRequestedPrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752499887;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'app', 'requested_privileges')) {
            // prevent new NULL entries
            $connection->executeStatement('
                ALTER TABLE `app`
                MODIFY COLUMN `requested_privileges` JSON DEFAULT (JSON_ARRAY())
            ');

            $connection->executeStatement('
                UPDATE `app`
                SET requested_privileges = JSON_ARRAY()
                WHERE requested_privileges IS NULL
            ');

            // all values are now guaranteed to be non-NULL, so we can change the column to NOT NULL.
            $connection->executeStatement('
                ALTER TABLE `app`
                MODIFY COLUMN `requested_privileges` JSON NOT NULL DEFAULT (JSON_ARRAY())
            ');
        }
    }
}
