<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1645453538AddRuleTag;

class Migration1645453538AddRuleTagTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->rollback();
    }

    public function testMigration(): void
    {
        $migration = new Migration1645453538AddRuleTag();
        $migration->update($this->connection);

        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns('rule_tag');

        static::assertNotEmpty($columns);
        static::assertArrayHasKey('rule_id', $columns);
        static::assertArrayHasKey('tag_id', $columns);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `rule_tag`');
    }
}
