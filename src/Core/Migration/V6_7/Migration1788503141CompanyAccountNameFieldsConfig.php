<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1788503141CompanyAccountNameFieldsConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788503141;
    }

    public function update(Connection $connection): void
    {
        // an absent key reads as false, so an upgraded shop would silently make the contact person
        // optional instead of keeping it mandatory
        foreach ([CompanyAccountNameFields::CONFIG_SHOW, CompanyAccountNameFields::CONFIG_REQUIRED] as $key) {
            $existing = $connection->fetchOne(
                'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ? AND `sales_channel_id` IS NULL',
                [$key]
            );

            if ($existing !== false) {
                continue;
            }

            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => $key,
                'configuration_value' => json_encode(['_value' => true], \JSON_THROW_ON_ERROR),
                'sales_channel_id' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }
}
