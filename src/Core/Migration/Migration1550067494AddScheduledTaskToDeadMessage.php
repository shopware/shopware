<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550067494AddScheduledTaskToDeadMessage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550067494;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `dead_message`
            ADD COLUMN `scheduled_task_id` BINARY(16) DEFAULT NULL AFTER exception_line,
            ADD CONSTRAINT `fk.dead_message.scheduled_task_id`
                FOREIGN KEY (scheduled_task_id) REFERENCES `scheduled_task` (id) ON DELETE SET NULL
            '
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
