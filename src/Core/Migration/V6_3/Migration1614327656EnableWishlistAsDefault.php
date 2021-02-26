<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1614327656EnableWishlistAsDefault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614327656;
    }

    public function update(Connection $connection): void
    {
        $configId = $connection->fetchColumn('SELECT id FROM system_config WHERE configuration_key = :key', [
            'key' => 'core.cart.wishlistEnabled',
        ]);

        if (!$configId) {
            return;
        }

        $connection->update('system_config', [
            'configuration_value' => json_encode(['_value' => true]),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'id' => $configId,
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
