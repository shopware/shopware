<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\ClassifyCrossMajorPartial\V6_91;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * @internal
 */
final class Migration2000000001Partial extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2000000001;
    }

    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'fixture.indexer', ['SomeUpdater']);
    }
}
