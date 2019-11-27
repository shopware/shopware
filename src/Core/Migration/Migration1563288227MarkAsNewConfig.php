<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1563288227MarkAsNewConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563288227;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.listing.markAsNew',
            'configuration_value' => '{"_value": "30"}',
            'created_at' => \date(Defaults::STORAGE_DATE_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
