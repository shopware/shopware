<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1764064756AddCustomFieldSearchable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1764064756AddCustomFieldSearchable::class)]
class Migration1764064756AddCustomFieldSearchableTest extends TestCase
{
    private readonly Connection $connection;

    private readonly Migration1764064756AddCustomFieldSearchable $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1764064756AddCustomFieldSearchable();

        try {
            $this->connection->executeStatement('ALTER TABLE `custom_field` DROP COLUMN `searchable`;');
        } catch (\Throwable) {
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1764064756, $this->migration->getCreationTimestamp());
    }

    public function testAddColumn(): void
    {
        $columns = $this->getTableColumns('custom_field');
        static::assertArrayNotHasKey('searchable', $columns);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $columns = $this->getTableColumns('custom_field');
        static::assertArrayHasKey('searchable', $columns);
        static::assertFalse($columns['searchable']['nullable']);
        static::assertSame('0', $columns['searchable']['default']);
    }

    /**
     * @return array<string, array{type: string, nullable: bool, default: string|null}>
     */
    private function getTableColumns(string $tableName): array
    {
        $columns = $this->connection->fetchAllAssociative(
            \sprintf('SHOW COLUMNS FROM `%s`', $tableName)
        );

        $result = [];
        foreach ($columns as $column) {
            $result[$column['Field']] = [
                'type' => $column['Type'],
                'nullable' => $column['Null'] === 'YES',
                'default' => $column['Default'],
            ];
        }

        return $result;
    }
}
