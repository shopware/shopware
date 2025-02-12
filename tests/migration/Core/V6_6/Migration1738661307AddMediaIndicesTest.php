<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
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

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
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

        static::assertTrue($this->hasIndex('idx.media.file_extension', ['file_extension']));
        static::assertTrue($this->hasIndex('idx.media.file_name', ['file_name']));
        static::assertTrue($this->hasColumn('file_hash'));
        static::assertTrue($this->hasIndex('idx.media.file_hash', ['file_hash']));
    }

    private function migrate(): void
    {
        (new Migration1738661307AddMediaIndices())->update($this->connection);
    }

    private function undoMigration(): void
    {
        if ($this->hasColumn('file_hash')) {
            $this->connection->executeStatement(
                <<<SQL
                ALTER TABLE `media` DROP COLUMN `file_hash`;
                SQL
            );
        }

        foreach (['idx.media.file_extension', 'idx.media.file_name', 'idx.media.file_hash'] as $indexName) {
            if ($this->hasIndex($indexName)) {
                $this->connection->executeStatement(
                    <<<SQL
                    ALTER TABLE `media` DROP INDEX `$indexName`;
                    SQL
                );
            }
        }
    }

    /**
     * @param list<string> $spansColumns Also test if the index covers the given columns
     */
    private function hasIndex(string $indexName, array $spansColumns = []): bool
    {
        $manager = $this->connection->createSchemaManager();
        $indices = $manager->listTableIndexes('media');

        return \array_key_exists($indexName, $indices)
            && $indices[$indexName]->spansColumns($spansColumns);
    }

    private function hasColumn(string $columnName): bool
    {
        return EntityDefinitionQueryHelper::columnExists($this->connection, 'media', $columnName);
    }
}
