<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1645453538AddRuleTag;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1645453538AddRuleTag
 */
class Migration1645453538AddRuleTagTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
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
