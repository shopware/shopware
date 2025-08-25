<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1742484083TransitionToAddressInputFieldArrangement extends MigrationStep
{
    public const NEW_CONFIG_KEY = 'core.loginRegistration.addressInputFieldArrangement';
    public const OLD_CONFIG_KEY = 'core.loginRegistration.showZipcodeInFrontOfCity';

    public function getCreationTimestamp(): int
    {
        return 1742484083;
    }

    public function update(Connection $connection): void
    {
        $oldConfiguration = $connection->fetchAllAssociativeIndexed(
            'SELECT sales_channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [self::OLD_CONFIG_KEY]
        );

        $newConfiguration = $connection->fetchAllAssociativeIndexed(
            'SELECT sales_channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [self::NEW_CONFIG_KEY]
        );

        foreach ($oldConfiguration as $salesChannelId => $oldValue) {
            if (isset($newConfiguration[$salesChannelId])) {
                continue;
            }

            $showZipcodeInFrontOfCity = (bool) \json_decode(
                $oldValue['configuration_value'],
                true,
                flags: \JSON_THROW_ON_ERROR,
            )['_value'];

            $newValue = match ($showZipcodeInFrontOfCity) {
                true => 'zip-city-state',
                false => 'city-zip-state',
            };

            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => self::NEW_CONFIG_KEY,
                'configuration_value' => json_encode(['_value' => $newValue]),
                'sales_channel_id' => $salesChannelId ?: null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->delete('system_config', ['configuration_key' => self::OLD_CONFIG_KEY]);
    }
}
