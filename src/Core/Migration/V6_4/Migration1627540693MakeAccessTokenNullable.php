<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1627540693MakeAccessTokenNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1627540693;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `import_export_file` CHANGE `access_token` `access_token` varchar(255) COLLATE \'utf8mb4_unicode_ci\' NULL AFTER `created_at`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
