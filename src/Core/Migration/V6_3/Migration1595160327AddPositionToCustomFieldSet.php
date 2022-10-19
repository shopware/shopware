<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1595160327AddPositionToCustomFieldSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595160327;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `custom_field_set`
            ADD COLUMN `position` INT(11) NOT NULL DEFAULT 1 AFTER `active`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
