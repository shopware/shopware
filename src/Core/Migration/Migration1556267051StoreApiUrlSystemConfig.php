<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1556267051StoreApiUrlSystemConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556267051;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.store.apiUri',
            'configuration_value' => 'https://api.shopware.com',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
