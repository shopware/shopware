<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1671723392AddWebhookLifetimeConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1671723392;
    }

    public function update(Connection $connection): void
    {
        $config = $connection->fetchAssociative(
            'SELECT * FROM system_config WHERE configuration_key = \'core.webhook.entryLifetimeSeconds\''
        );

        if ($config !== false) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.webhook.entryLifetimeSeconds',
            'configuration_value' => '{"_value": "1209600"}', // 2 weeks
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
