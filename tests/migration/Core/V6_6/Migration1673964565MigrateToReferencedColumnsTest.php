<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1673964565MigrateToReferencedColumns;

/**
 * @internal
 */
#[CoversClass(Migration1673964565MigrateToReferencedColumns::class)]
class Migration1673964565MigrateToReferencedColumnsTest extends TestCase
{
    private Connection $connection;

    private Migration1673964565MigrateToReferencedColumns $migration;

    protected function setUp(): void
    {
        $this->migration = new Migration1673964565MigrateToReferencedColumns();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1673964565', $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString('`referenced_id` binary(16) NOT NULL', $this->getSchema());
        static::assertStringContainsString('`referenced_version_id` binary(16) NOT NULL', $this->getSchema());
        static::assertStringNotContainsString('`entity_id`', $this->getSchema());
    }

    public function testUpdateTwice(): void
    {
        $this->migration->update($this->connection);
        $expected = $this->getSchema();

        static::assertStringContainsString('`referenced_id` binary(16) NOT NULL', $expected);
        static::assertStringContainsString('`referenced_version_id` binary(16) NOT NULL', $expected);
        static::assertStringNotContainsString('`entity_id`', $this->getSchema());

        $this->migration->update($this->connection);
        static::assertSame($expected, $this->getSchema());
    }

    /**
     * @throws \Throwable
     */
    private function getSchema(): string
    {
        $schema = $this->connection->fetchAssociative(sprintf('SHOW CREATE TABLE `%s`', 'state_machine_history'));
        static::assertNotFalse($schema);
        static::assertIsString($schema['Create Table']);

        return $schema['Create Table'];
    }
}
