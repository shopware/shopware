<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1738661307AddMediaIndices;

/**
 * @internal
 */
#[CoversClass(Migration1738661307AddMediaIndices::class)]
class Migration1738661307AddMediaIndicesTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private string $tableName = 'media';

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        // Test multiple execution
        $this->migrate();
        $this->migrate();

        static::assertTrue($this->hasIndex('idx.media.file_extension', ['file_extension']));
        static::assertTrue($this->hasIndex('idx.media.file_name', ['file_name']));
        static::assertTrue($this->hasColumn('file_hash'));
        static::assertTrue($this->hasIndex('idx.media.file_hash', ['file_hash']));
    }

    private function migrate(): void
    {
        (new Migration1738661307AddMediaIndices())->update($this->connection);
    }

    /**
     * @param list<string> $spansColumns Also test if the index covers the given columns
     */
    private function hasIndex(string $indexName, array $spansColumns = []): bool
    {
        $manager = $this->connection->createSchemaManager();
        $indices = $manager->listTableIndexes($this->tableName);

        return \array_key_exists($indexName, $indices)
            && $indices[$indexName]->spansColumns($spansColumns);
    }

    private function hasColumn(string $columnName): bool
    {
        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns($this->tableName);

        return \array_key_exists($columnName, $columns);
    }
}
