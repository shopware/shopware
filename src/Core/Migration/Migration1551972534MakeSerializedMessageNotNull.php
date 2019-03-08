<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551972534MakeSerializedMessageNotNull extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551972534;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $query = <<<SQL
ALTER TABLE `dead_message`
MODIFY `serialized_original_message` LONGBLOB NOT NULL;
SQL;

        $connection->executeQuery($query);
    }
}
