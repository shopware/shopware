<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\_test_trigger_with_trigger_;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class MigrationWithBackwardTrigger extends MigrationStep
{
    public const TRIGGER_NAME = 'testBackwardTrigger';

    /**
     * get creation timestamp
     */
    public function getCreationTimestamp(): int
    {
        return 2;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
        $trigger = $this->getTrigger();

        $this->addBackwardTrigger(
            $connection,
            $trigger['name'],
            $trigger['table'],
            $trigger['time'],
            $trigger['event'],
            $trigger['statement']
        );
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::TRIGGER_NAME);
    }

    private function getTrigger(): array
    {
        return [
            'name' => self::TRIGGER_NAME,
            'table' => 'migration',
            'time' => 'BEFORE',
            'event' => 'INSERT',
            'statement' => 'SET NEW.`creation_timestamp` = NEW.`creation_timestamp` + 1',
        ];
    }
}
