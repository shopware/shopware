<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1537420769AddFileName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1537420769;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
            ADD COLUMN `file_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');

        $connection->executeQuery('
            UPDATE `media`
            SET `file_name` = `id`;
        ');

        foreach ($this->getTrigger() as $trigger) {
            $this->addForwardTrigger(
                $connection,
                $trigger['name'],
                $trigger['table'],
                $trigger['time'],
                $trigger['event'],
                $trigger['statement']
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        foreach ($this->getTrigger() as $trigger) {
            $this->removeTrigger($connection, $trigger['name']);
        }
    }

    private function getTrigger(): array
    {
        return [
            [
                'name' => 'trigger_1537420769_copy_id_to_filename_insert',
                'table' => 'media',
                'time' => 'BEFORE',
                'event' => 'INSERT',
                'statement' => 'SET NEW.file_name = NEW.id',
            ],
            [
                'name' => 'trigger_1537420769_copy_id_to_filename_update',
                'table' => 'media',
                'time' => 'BEFORE',
                'event' => 'UPDATE',
                'statement' => 'SET NEW.file_name = NEW.id',
            ],
        ];
    }
}
