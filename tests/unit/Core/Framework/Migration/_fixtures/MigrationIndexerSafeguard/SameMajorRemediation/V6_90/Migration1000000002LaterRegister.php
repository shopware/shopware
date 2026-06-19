<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\SameMajorRemediation\V6_90;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * @internal
 */
final class Migration1000000002LaterRegister extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000002;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'fixture.indexer');
    }
}
