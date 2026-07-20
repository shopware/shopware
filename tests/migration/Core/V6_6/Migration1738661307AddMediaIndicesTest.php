<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1738661307AddMediaIndices;

/**
 * @internal
 */
#[CoversClass(Migration1738661307AddMediaIndices::class)]
class Migration1738661307AddMediaIndicesTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1738661307, (new Migration1738661307AddMediaIndices())->getCreationTimestamp());
    }

    public function testTimestamp(): void
    {
        static::assertSame(1738661307, (new Migration1738661307AddMediaIndices())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->undoMigration();
        // Test multiple execution
        $this->migrate();
        $this->migrate();

        static::assertTrue($this->hasIndex($this->connection, 'idx.media.file_extension', ['file_extension']));
        static::assertTrue($this->hasIndex($this->connection, 'idx.media.file_name', ['file_name']));
        static::assertTrue(TableHelper::columnExists($this->connection, 'media', 'file_hash'));
        static::assertTrue($this->hasIndex($this->connection, 'idx.media.file_hash', ['file_hash']));
    }

    private function migrate(): void
    {
        (new Migration1738661307AddMediaIndices())->update($this->connection);
    }

    private function undoMigration(): void
    {
        if (TableHelper::columnExists($this->connection, 'media', 'file_hash')) {
            $this->connection->executeStatement('ALTER TABLE `media` DROP COLUMN `file_hash`;');
        }

        foreach (['idx.media.file_extension', 'idx.media.file_name', 'idx.media.file_hash'] as $indexName) {
            if (TableHelper::indexExists($this->connection, 'media', $indexName)) {
                $this->connection->executeStatement("ALTER TABLE `media` DROP INDEX `$indexName`;");
            }
        }
    }

    /**
     * @param list<string> $spansColumns Also test if the index covers the given columns
     */
    private function hasIndex(Connection $connection, string $indexName, array $spansColumns = []): bool
    {
        return TableHelper::indexSpansColumns($connection, 'media', $indexName, $spansColumns);
    }
}
