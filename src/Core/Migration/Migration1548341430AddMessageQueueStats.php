<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548341430AddMessageQueueStats extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548341430;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
          CREATE TABLE message_queue_stats (
            `id` binary(16) NOT NULL PRIMARY KEY,          
            `name` varchar(255) NOT NULL,
            `size` int(11) NOT NULL DEFAULT \'0\',
              
            CONSTRAINT `uniq.message_queue_stats.name` 
              UNIQUE(`name`),
              
            CONSTRAINT `min.message_queue_stats.size` 
               CHECK (size >= 0)
        )');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
