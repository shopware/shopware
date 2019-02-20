<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550570085AddThumbanilSizesToDefaultFolder extends MigrationStep
{
    const FORWARD_TRIGGER_NAME = 'TRIGGER_1550570085_FORWARD';

    public function getCreationTimestamp(): int
    {
        return 1550570085;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_default_folder`
            ADD COLUMN `thumbnail_sizes` LONGTEXT AFTER association_fields,
            ADD CONSTRAINT `json.thumbnail_sizes` CHECK (JSON_VALID(`thumbnail_sizes`));
            '
        );

        $connection->exec('
            UPDATE `media_default_folder` 
            SET thumbnail_sizes = "[]"
        ');

        $connection->exec('
            UPDATE `media_default_folder` 
            SET thumbnail_sizes = "[{\"width\": 150, \"height\": 150}, {\"width\": 300, \"height\": 300}, {\"width\": 600, \"height\": 600}]"
            WHERE entity = "product"
        ');

        $this->addForwardTrigger(
            $connection,
            self::FORWARD_TRIGGER_NAME,
            'media_default_folder',
            'BEFORE',
            'INSERT',
            'SET NEW.thumbnail_sizes = "[]"'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, self::FORWARD_TRIGGER_NAME);
    }
}
