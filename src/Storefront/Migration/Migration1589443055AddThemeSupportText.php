<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1589443055AddThemeSupportText extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589443055;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `theme_translation` ADD `support_texts` json NULL AFTER `labels`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
