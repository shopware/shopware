<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1776816100AddSeoUrlPathInfoCanonicalIndex;

/**
 * @internal
 */
#[CoversClass(Migration1776816100AddSeoUrlPathInfoCanonicalIndex::class)]
class Migration1776816100AddSeoUrlPathInfoCanonicalIndexTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testTimestamp(): void
    {
        static::assertSame(1776816100, (new Migration1776816100AddSeoUrlPathInfoCanonicalIndex())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->undoMigration();

        $migration = new Migration1776816100AddSeoUrlPathInfoCanonicalIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'seo_url',
            'idx.path_info.is_canonical',
            ['path_info', 'is_canonical']
        ));
    }

    private function undoMigration(): void
    {
        if (!TableHelper::indexExists($this->connection, 'seo_url', 'idx.path_info.is_canonical')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `seo_url` DROP INDEX `idx.path_info.is_canonical`');
    }
}
