<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610014592SalutationCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610014592;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `salutation_translation`
            ADD COLUMN `custom_fields` JSON NULL AFTER `letter_name`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
