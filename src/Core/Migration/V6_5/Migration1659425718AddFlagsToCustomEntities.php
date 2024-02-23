<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('content')]
class Migration1659425718AddFlagsToCustomEntities extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1659425718;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'custom_entity',
            'flags',
            'JSON'
        );
    }
}
