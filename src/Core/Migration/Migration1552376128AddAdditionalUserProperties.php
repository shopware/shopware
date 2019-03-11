<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552376128AddAdditionalUserProperties extends MigrationStep
{
    const FORWARD_TRIGGER_NAME = 'forward_trigger_1552376128';
    const BACKWARD_TRIGGER_NAME = 'backward_trigger_1552376128';

    public function getCreationTimestamp(): int
    {
        return 1552376128;
    }

    public function update(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `user`
             ADD COLUMN `first_name` varchar(255) NOT NULL AFTER `name`,
             ADD COLUMN `last_name` varchar(255) NOT NULL AFTER `first_name`
            '
        );

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME,
            'user',
            'BEFORE',
            'INSERT',
            '
            SET new.last_name = new.name;
            SET new.first_name = ""
            '
        );

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME,
            'user',
            'BEFORE',
            'INSERT',
            'SET new.name = new.last_name'
        );

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME . 'UPDATE',
            'user',
            'BEFORE',
            'UPDATE',
            'SET new.last_name = new.name'
        );

        $this->addBackwardTrigger(
            $connection,
            self::BACKWARD_TRIGGER_NAME . 'UPDATE',
            'user',
            'BEFORE',
            'UPDATE',
            'SET new.name = new.last_name'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME);
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME);
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME . 'UPDATE');
        $this->removeTrigger($connection, self::BACKWARD_TRIGGER_NAME . 'UPDATE');

        $connection->exec('ALTER TABLE `user` DROP COLUMN `name`');
    }
}
