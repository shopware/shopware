<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\_test_migrations_valid_trigger;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Migration\Trigger;
use Shopware\Core\Framework\Migration\TriggerDirection;
use Shopware\Core\Framework\Migration\TriggerEvent;
use Shopware\Core\Framework\Migration\TriggerTime;

class Migration1 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    public function update(Connection $connection): void
    {
        //nth
    }

    public function updateDestructive(Connection $connection): void
    {
        //nth
    }

    protected function getTrigger(): array
    {
        return [
            new Trigger(
                'testTriggerInsert',
                TriggerTime::BEFORE,
                TriggerEvent::INSERT,
                TriggerDirection::BACKWARD,
                'migration',
                'SET NEW.class = NEW.class'
            ),
            new Trigger(
                'testTriggerUpdate',
                TriggerTime::BEFORE,
                TriggerEvent::UPDATE,
                TriggerDirection::BACKWARD,
                'migration',
                'SET NEW.class = NEW.class'
            ),
        ];
    }
}
