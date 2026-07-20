<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Command\_fixtures;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class InvalidMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772030791;
    }

    public function update(Connection $connection): void
    {
    }
}
