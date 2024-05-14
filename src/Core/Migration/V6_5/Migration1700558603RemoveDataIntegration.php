<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @phpstan-type SystemConfig array{integrationId: string, appUrl: string, shopId: string}
 */
#[Package('core')]
class Migration1700558603RemoveDataIntegration extends MigrationStep
{
    final public const SYSTEM_CONFIG_KEY = 'core.usageData.integration';

    public function getCreationTimestamp(): int
    {
        return 1700558603;
    }

    public function update(Connection $connection): void
    {
        $systemConfig = $this->fetchSystemConfig($connection);
        if ($systemConfig === null) {
            return;
        }

        $connection->executeStatement(
            'DELETE FROM `integration` WHERE `id` = :integrationId',
            ['integrationId' => $systemConfig['integrationId']]
        );

        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE configuration_key = :configurationKey',
            ['configurationKey' => self::SYSTEM_CONFIG_KEY]
        );
    }

    /**
     * @return SystemConfig
     */
    private function fetchSystemConfig(Connection $connection): ?array
    {
        $systemConfig = $connection->executeQuery(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :configurationKey',
            ['configurationKey' => self::SYSTEM_CONFIG_KEY]
        )->fetchOne();

        if (!\is_string($systemConfig)) {
            return null;
        }

        return json_decode($systemConfig, true, flags: \JSON_THROW_ON_ERROR)['_value'];
    }
}
