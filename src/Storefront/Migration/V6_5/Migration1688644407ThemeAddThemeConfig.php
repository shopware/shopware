<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1688644407ThemeAddThemeConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688644407;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `theme` ADD `theme_json` JSON NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
