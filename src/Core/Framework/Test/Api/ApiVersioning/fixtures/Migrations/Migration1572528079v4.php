<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Migrations;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1572528079v4 extends MigrationStep
{
    private const BACKWARD_UPDATE_TRIGGER_NAME = 'Migration1572528079v4UpdateBundleTriggerBackward';
    private const FORWARD_UPDATE_TRIGGER_NAME = 'Migration1572528079v4UpdateBundleTriggerForward';
    private const BACKWARD_INSERT_TRIGGER_NAME = 'Migration1572528079v4InsertBundleTriggerBackward';
    private const FORWARD_INSERT_TRIGGER_NAME = 'Migration1572528079v4InsertBundleTriggerForward';

    public function getCreationTimestamp(): int
    {
        return 1572528079;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE _test_bundle_price
            ADD COLUMN `pseudo_price` DOUBLE NOT NULL DEFAULT 0.0 AFTER `price`;
        ');

        $connection->executeUpdate('
            UPDATE _test_bundle_price
            SET `pseudo_price` = (SELECT bundle.pseudo_price FROM _test_bundle AS bundle WHERE bundle.id = bundle_id)
            WHERE `quantity_start` = 0;
        ');

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_UPDATE_TRIGGER_NAME,
            '_test_bundle_price',
            'BEFORE',
            'UPDATE',
            '
                IF (NEW.quantity_start = 0)
                THEN    
                    UPDATE `_test_bundle`
                    SET `pseudo_price` = NEW.pseudo_price
                    WHERE `id` = NEW.bundle_id;
                END IF
            '
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_UPDATE_TRIGGER_NAME,
            '_test_bundle',
            'BEFORE',
            'UPDATE',
            ' 
                UPDATE `_test_bundle_price`
                SET `pseudo_price` = NEW.pseudo_price
                WHERE `bundle_id` = NEW.id AND `quantity_start` = 0
            '
        );
        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_INSERT_TRIGGER_NAME,
            '_test_bundle_price',
            'BEFORE',
            'INSERT',
            '
                IF (NEW.quantity_start = 0)
                THEN    
                    UPDATE `_test_bundle`
                    SET `pseudo_price` = NEW.pseudo_price
                    WHERE `id` = NEW.bundle_id;
                END IF
            '
        );
        $this->addForwardTrigger(
            $connection,
            self::FORWARD_INSERT_TRIGGER_NAME,
            '_test_bundle',
            'AFTER',
            'INSERT',
            ' 
                UPDATE `_test_bundle_price`
                SET `pseudo_price` = NEW.pseudo_price
                WHERE `bundle_id` = NEW.id AND `quantity_start` = 0
            '
        );
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE _test_bundle
            DROP COLUMN `pseudo_price`;
        ');
    }
}
