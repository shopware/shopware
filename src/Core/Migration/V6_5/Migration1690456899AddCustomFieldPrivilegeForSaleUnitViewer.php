<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1690456899AddCustomFieldPrivilegeForSaleUnitViewer extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'scale_unit.viewer' => [
            'custom_field_set:read',
            'custom_field:read',
            'custom_field_set_relation:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1690456899;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
