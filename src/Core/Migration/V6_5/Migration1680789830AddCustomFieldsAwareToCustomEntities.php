<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1680789830AddCustomFieldsAwareToCustomEntities extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1680789830;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'custom_entity',
            'custom_fields_aware',
            'TINYINT(1)',
            false,
            '0'
        );

        $this->addColumn(
            $connection,
            'custom_entity',
            'label_property',
            'VARCHAR(255)'
        );
    }
}
