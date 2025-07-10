<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class Migration1751623078AddMeasurementSystemPrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'measurement.viewer' => [
            'system_config:read',
            'measurement_system:read',
            'measurement_display_unit:read',
        ],
        'measurement.editor' => [
            'system_config:update',
        ],
        'measurement.creator' => [
            'measurement_system:create',
            'measurement_display_unit:create',
        ],
        'measurement.deleter' => [
            'measurement_system:delete',
            'measurement_display_unit:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1751623078;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
