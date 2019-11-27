<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1561712450NewSystemConfigsAndDefaultValues extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561712450;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.cart.maxQuantity',
            'configuration_value' => '{"_value": "100"}',
            'created_at' => \date(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $builder = $connection->createQueryBuilder()->select('id')
            ->from('system_config')
            ->where('configuration_key = "core.loginRegistration.passwordMinLength"');

        $configId = $builder->execute()->fetchColumn();
        if (!$configId) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.loginRegistration.passwordMinLength',
                'configuration_value' => '{"_value": "8"}',
                'created_at' => \date(Defaults::STORAGE_DATE_FORMAT),
            ]);
        }

        $builder = $connection->createQueryBuilder()->select('id')
            ->from('system_config')
            ->where('configuration_key = "core.address.showZipcodeInFrontOfCity"');

        $configId = $builder->execute()->fetchColumn();
        if (!$configId) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.address.showZipcodeInFrontOfCity',
                'configuration_value' => '{"_value": true}',
                'created_at' => \date(Defaults::STORAGE_DATE_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
