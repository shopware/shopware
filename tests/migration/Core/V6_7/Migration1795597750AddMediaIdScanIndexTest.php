<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1795597750AddMediaIdScanIndex;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1795597750AddMediaIdScanIndex::class)]
class Migration1795597750AddMediaIdScanIndexTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMediaIteratorUsesCoveringIndexWhenAvailable(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $factory = new IteratorFactory($connection, static::getContainer()->get(DefinitionInstanceRegistry::class));
        $migration = new Migration1795597750AddMediaIdScanIndex();

        if (TableHelper::indexExists($connection, 'media', 'idx_media_id_only')) {
            $connection->executeStatement('DROP INDEX `idx_media_id_only` ON `media`');
        }

        try {
            $before = $factory->createIterator(MediaDefinition::ENTITY_NAME, limit: 2);
            $expectedCount = $before->fetchCount();
            $expectedIds = $before->fetch();
            $expectedNextIds = $before->fetch();
            static::assertStringNotContainsString('FORCE INDEX', $before->getQuery()->getSQL());
        } finally {
            $migration->update($connection);
        }

        $migration->update($connection);
        $columns = $connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
            ['media', 'idx_media_id_only'],
        );
        static::assertSame(['id'], $columns);
        $after = $factory->createIterator(MediaDefinition::ENTITY_NAME, limit: 2);
        static::assertSame($expectedCount, $after->fetchCount());
        static::assertSame($expectedIds, $after->fetch());

        $query = $after->getQuery();
        $plan = $connection->fetchAssociative('EXPLAIN ' . $query->getSQL(), $query->getParameters(), $query->getParameterTypes());
        static::assertIsArray($plan);
        static::assertSame('idx_media_id_only', $plan['key']);
        static::assertStringContainsString('Using index', $plan['Extra']);

        // A DBA may disable the index while a worker is paging.
        $maria = $connection->getDatabasePlatform() instanceof MariaDBPlatform;
        $connection->executeStatement('ALTER TABLE media ALTER INDEX idx_media_id_only ' . ($maria ? 'IGNORED' : 'INVISIBLE'));
        try {
            static::assertSame($expectedNextIds, $after->fetch());
            static::assertStringNotContainsString('FORCE INDEX', $after->getQuery()->getSQL());
        } finally {
            $connection->executeStatement('ALTER TABLE media ALTER INDEX idx_media_id_only ' . ($maria ? 'NOT IGNORED' : 'VISIBLE'));
        }

        $joined = $factory->createIterator(MediaDefinition::ENTITY_NAME);
        $joined->getQuery()->leftJoin('`media`', 'media_folder', 'folder', 'folder.id = media.media_folder_id');
        static::assertStringNotContainsString('FORCE INDEX', $joined->getQuery()->getSQL());
        static::assertSame($expectedCount, $joined->fetchCount());

        $unhinted = $factory->createIterator('product');
        static::assertStringNotContainsString('FORCE INDEX', $unhinted->getQuery()->getSQL());
    }
}
