<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1734690547SetDefaultAllowAccountLookupSetting extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1734690547;
    }

    public function update(Connection $connection): void
    {
        $config = $connection->fetchAssociative(
            'SELECT * FROM system_config WHERE configuration_key = \'core.loginRegistration.allowAccountLookup\''
        );

        if ($config !== false) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.loginRegistration.allowAccountLookup',
            'configuration_value' => '{"_value": true}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
