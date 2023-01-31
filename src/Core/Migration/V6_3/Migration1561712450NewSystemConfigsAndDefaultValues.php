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
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $builder = $connection->createQueryBuilder()->select('id')
            ->from('system_config')
            ->where('configuration_key = "core.loginRegistration.passwordMinLength"');

        $configId = $builder->executeQuery()->fetchOne();
        if (!$configId) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.loginRegistration.passwordMinLength',
                'configuration_value' => '{"_value": "8"}',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $builder = $connection->createQueryBuilder()->select('id')
            ->from('system_config')
            ->where('configuration_key = "core.address.showZipcodeInFrontOfCity"');

        $configId = $builder->executeQuery()->fetchOne();
        if (!$configId) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => 'core.address.showZipcodeInFrontOfCity',
                'configuration_value' => '{"_value": true}',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
