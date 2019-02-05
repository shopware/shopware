<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549355553CmsSlotJsonCheck extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549355553;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `cms_slot_translation` ADD CONSTRAINT `json.config` CHECK(JSON_VALID(`config`))
SQL;

        $connection->exec($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
