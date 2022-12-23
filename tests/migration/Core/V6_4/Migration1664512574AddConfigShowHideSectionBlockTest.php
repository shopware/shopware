<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1664512574AddConfigShowHideSectionBlock;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1664512574AddConfigShowHideSectionBlock
 */
class Migration1664512574AddConfigShowHideSectionBlockTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->removeColumn('cms_section');
        $this->removeColumn('cms_block');
        $migration = new Migration1664512574AddConfigShowHideSectionBlock();
        $migration->update($this->connection);
    }

    /**
     * @dataProvider tableProvider
     */
    public function testMigrationColumn(string $tableName): void
    {
        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns($tableName);

        static::assertNotEmpty($columns);
        static::assertArrayHasKey('visibility', $columns);
    }

    /**
     * @return iterable<array<string>>
     */
    public function tableProvider(): iterable
    {
        yield ['cms_block'];
        yield ['cms_section'];
    }

    private function removeColumn(string $tableName): void
    {
        if ($this->hasColumn($tableName)) {
            $this->connection->executeStatement(\sprintf('ALTER TABLE `%s` DROP COLUMN `visibility`', $tableName));
        }
    }

    private function hasColumn(string $table): bool
    {
        return \in_array('visibility', array_column($this->connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field'), true);
    }
}
