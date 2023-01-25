<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1563785071AddThemeHelpText extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563785071;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `theme_translation` ADD `help_texts` json NULL AFTER `labels`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
