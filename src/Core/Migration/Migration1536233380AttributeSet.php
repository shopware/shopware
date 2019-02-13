<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233380AttributeSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233380;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `attribute_set` (
              `id` BINARY(16) NOT NULL PRIMARY KEY,
              `config` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3),
              CONSTRAINT `json.config` CHECK(JSON_VALID(`config`))
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
