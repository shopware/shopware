<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
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
            $this->connection->executeStatement('ALTER TABLE `custom_field` DROP COLUMN `include_in_search`;');
        } catch (\Throwable) {
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1764064756, $this->migration->getCreationTimestamp());
    }

    public function testAddColumn(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, 'custom_field', 'include_in_search'));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'custom_field', 'include_in_search');
        static::assertTrue($column->isNotNull);
        static::assertSame('0', $column->defaultValue);
    }
}
