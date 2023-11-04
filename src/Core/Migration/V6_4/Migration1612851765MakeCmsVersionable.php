<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MakeVersionableMigrationHelper;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1612851765MakeCmsVersionable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612851765;
    }

    public function update(Connection $connection): void
    {
        $playbookGenerator = new MakeVersionableMigrationHelper($connection);

        $tables = [
            'cms_page',
            'cms_section',
            'cms_block',
        ];

        foreach ($tables as $table) {
            $hydratedData = $playbookGenerator->getRelationData($table, 'id');
            $playbook = $playbookGenerator->createSql($hydratedData, $table, 'version_id', Defaults::LIVE_VERSION);

            foreach ($playbook as $query) {
                $connection->executeStatement($query);
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do here
    }
}
