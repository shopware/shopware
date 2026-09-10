<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1788964043AddRefreshTokenFamily extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788964043;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'refresh_token', 'family_id', 'BINARY(16)');
        $this->addColumn($connection, 'refresh_token', 'revoked_at', 'DATETIME(3)');

        if (!TableHelper::indexExists($connection, 'refresh_token', 'idx.refresh_token.family_id')) {
            $connection->executeStatement('CREATE INDEX `idx.refresh_token.family_id` ON `refresh_token` (`family_id`)');
        }
    }
}
