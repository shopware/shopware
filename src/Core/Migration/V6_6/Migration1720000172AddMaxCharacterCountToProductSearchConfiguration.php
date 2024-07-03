<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1720000172AddMaxCharacterCountToProductSearchConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720000172;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_search_config', 'max_character_count', 'smallint', false, '60');
    }
}
