<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MakeVersionableMigrationHelper;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1790152364MakeContentLayoutVersionable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790152364;
    }

    public function update(Connection $connection): void
    {
        if (EntityDefinitionQueryHelper::columnExists($connection, 'content_layout', 'version_id')) {
            return;
        }

        $helper = new MakeVersionableMigrationHelper($connection);
        $playbook = $helper->createSql(
            $helper->getRelationData('content_layout', 'id'),
            'content_layout',
            'version_id',
            Defaults::LIVE_VERSION,
        );

        foreach ($playbook as $query) {
            $connection->executeStatement($query);
        }

        // A draft clones name and version, so the key must include the DAL version.
        $this->dropIndexIfExists($connection, 'content_layout', 'uniq.content_layout.name_version');
        $connection->executeStatement(
            'ALTER TABLE `content_layout` ADD UNIQUE INDEX `uniq.content_layout.name_version` (`name`, `version`, `version_id`)'
        );
    }
}
