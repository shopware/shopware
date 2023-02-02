<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1570629862ClearCategoryBreadcrumbs extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570629862;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `category_translation` SET `breadcrumb` = NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
