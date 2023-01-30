<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1617896006MakeNameNullable;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1617896006MakeNameNullable
 */
class Migration1617896006MakeNameNullableTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        parent::setup();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1617896006MakeNameNullable();
        $migration->update($connection);
    }

    public function testNameColumnIsNullable(): void
    {
        $schema = $this->connection->getSchemaManager();

        $column = array_filter($schema->listTableColumns('cms_page_translation'), static fn (Column $column): bool => $column->getName() === 'name');

        static::assertFalse($column['name']->getNotnull());
    }
}
