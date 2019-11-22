<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1563785071AddThemeHelpText extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563785071;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `theme_translation` ADD `help_texts` json NULL AFTER `labels`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
