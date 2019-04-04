<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1553784849AddDoubleOptInConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553784849;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'namespace' => 'privacy',
            'configuration_key' => 'doi_enabled',
            'configuration_value' => true,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
