<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1571754409v2 extends MigrationStep
{
    private const BACKWARD_UPDATE_TRIGGER_NAME = 'Migration1571754409v2UpdateBundleTriggerBackward';
    private const FORWARD_UPDATE_TRIGGER_NAME = 'Migration1571754409v2UpdateBundleTriggerForward';
    private const BACKWARD_INSERT_TRIGGER_NAME = 'Migration1571754409v2InsertBundleTriggerBackward';
    private const FORWARD_INSERT_TRIGGER_NAME = 'Migration1571754409v2InsertBundleTriggerForward';

    public function getCreationTimestamp(): int
    {
        return 1571754409;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `_test_bundle` 
            ADD COLUMN `is_absolute` TINYINT(1) NOT NULL DEFAULT 0 AFTER `discount_type`;
        ');

        $connection->executeUpdate('
            UPDATE `_test_bundle` 
            SET `is_absolute` = 1
            WHERE `discount_type` = "absolute"
        ');

        $connection->executeUpdate('
            ALTER TABLE `_test_bundle` 
            ALTER COLUMN `is_absolute` DROP DEFAULT;
        ');

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_UPDATE_TRIGGER_NAME,
            '_test_bundle',
            'BEFORE',
            'UPDATE',
            'SET NEW.discount_type = IF(NEW.is_absolute, "absolute", "percentage")'
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_UPDATE_TRIGGER_NAME,
            '_test_bundle',
            'BEFORE',
            'UPDATE',
            'SET NEW.is_absolute = IF(NEW.discount_type = "absolute", 1, 0)'
        );
        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_INSERT_TRIGGER_NAME,
            '_test_bundle',
            'BEFORE',
            'INSERT',
            'SET NEW.discount_type = IF(NEW.is_absolute, "absolute", "percentage")'
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_INSERT_TRIGGER_NAME,
            '_test_bundle',
            'BEFORE',
            'INSERT',
            'SET NEW.is_absolute = IF(NEW.discount_type = "absolute", 1, 0)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::BACKWARD_UPDATE_TRIGGER_NAME);
        $this->removeTrigger($connection, self::FORWARD_UPDATE_TRIGGER_NAME);
        $this->removeTrigger($connection, self::BACKWARD_INSERT_TRIGGER_NAME);
        $this->removeTrigger($connection, self::FORWARD_INSERT_TRIGGER_NAME);

        $connection->executeUpdate('
            ALTER TABLE `_test_bundle` 
            DROP COLUMN `discount_type`,
            DROP COLUMN `long_description`;
        ');
    }
}
