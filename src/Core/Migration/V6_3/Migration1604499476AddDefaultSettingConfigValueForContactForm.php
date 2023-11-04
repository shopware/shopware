<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1604499476AddDefaultSettingConfigValueForContactForm extends MigrationStep
{
    private const CONFIG_KEYS = [
        'core.basicInformation.firstNameFieldRequired',
        'core.basicInformation.lastNameFieldRequired',
        'core.basicInformation.phoneNumberFieldRequired',
    ];

    public function getCreationTimestamp(): int
    {
        return 1604499476;
    }

    public function update(Connection $connection): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach (self::CONFIG_KEYS as $configKey) {
            $configPresent = $connection->fetchOne('SELECT 1 FROM `system_config` WHERE `configuration_key` = ?', [$configKey]);

            if ($configPresent !== false) {
                continue;
            }

            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => $configKey,
                'configuration_value' => '{"_value": true}',
                'created_at' => $createdAt,
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
