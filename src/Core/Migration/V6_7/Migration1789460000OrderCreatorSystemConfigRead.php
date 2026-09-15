<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1789460000OrderCreatorSystemConfigRead extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order.creator' => [
            'system_config:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1789460000;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
