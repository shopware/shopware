<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730911642;
    }

    public function update(Connection $connection): void
    {
        $connection->update('system_config', [
            'configuration_key' => 'core.loginRegistration.showZipcodeInFrontOfCity',
        ], [
            'configuration_key' => 'core.address.showZipcodeInFrontOfCity',
        ]);
    }
}
