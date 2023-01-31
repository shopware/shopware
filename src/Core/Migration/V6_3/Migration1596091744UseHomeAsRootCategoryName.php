<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1596091744UseHomeAsRootCategoryName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1596091744;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE category_translation SET `name` = "Home" WHERE `name` IN ("Catalogue #1", "Katalog #1") AND updated_at IS NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
