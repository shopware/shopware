<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1572421282AddDoubleOptInRegistration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572421282;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `customer`
            ADD COLUMN `doubleOptInRegistration` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`,
            ADD COLUMN `doubleOptInEmailSentDate` DATETIME(3) NULL AFTER `doubleOptInRegistration`,
            ADD COLUMN `doubleOptInConfirmDate` DATETIME(3) NULL AFTER `doubleOptInEmailSentDate`,
            ADD COLUMN `hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL UNIQUE AFTER `doubleOptInConfirmDate`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
