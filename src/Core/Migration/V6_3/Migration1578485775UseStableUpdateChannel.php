<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1578485775UseStableUpdateChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578485775;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE system_config
             SET configuration_value = :value
             WHERE configuration_key = :key',
            [
                'key' => 'core.update.channel',
                'value' => json_encode(['_value' => 'stable']),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
