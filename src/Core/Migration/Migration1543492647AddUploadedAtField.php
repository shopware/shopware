<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1543492647AddUploadedAtField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1543492647;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media`
            ADD COLUMN `uploaded_at` datetime(3) AFTER `updated_at`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
