<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1646817331AddCmsClassColumnCmsPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1646817331;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'cms_page', 'css_class')) {
            $connection->executeStatement('ALTER TABLE `cms_page` ADD `css_class` VARCHAR(255) NULL AFTER `locked`;');
        }
    }
}
