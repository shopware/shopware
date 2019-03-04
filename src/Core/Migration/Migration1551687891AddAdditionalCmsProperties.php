<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551687891AddAdditionalCmsProperties extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551687891;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `cms_page`
ADD COLUMN `config` JSON AFTER `entity`,
ADD CONSTRAINT `json.config` CHECK (JSON_VALID(`config`))
SQL;

        $connection->exec($sql);

        $sql = <<<SQL
ALTER TABLE `cms_block`
ADD COLUMN `config` JSON AFTER `type`,
ADD CONSTRAINT `json.config` CHECK (JSON_VALID(`config`))
SQL;

        $connection->exec($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
