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
class Migration1759390536CartConfigShowTosCheckbox extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759390536;
    }

    public function update(Connection $connection): void
    {
        $this->insertConfig($connection, 'core.cart.showTosCheckbox');
    }

    private function insertConfig(Connection $connection, string $key): void
    {
        $config = $connection->fetchAllAssociativeIndexed(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ? and `sales_channel_id` is null',
            [$key]
        );

        if ($config !== []) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => $key,
            'configuration_value' => json_encode(['_value' => false]),
            'sales_channel_id' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
