<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1617896006MakeNameNullable;

class Migration1617896006MakeNameNullableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        parent::setup();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $migration = new Migration1617896006MakeNameNullable();
        $migration->update($connection);
    }

    public function testNameColumnIsNullable(): void
    {
        $schema = $this->connection->getSchemaManager();

        $column = array_filter($schema->listTableColumns('cms_page_translation'), static function (Column $column): bool {
            return $column->getName() === 'name';
        });

        static::assertFalse($column['name']->getNotnull());
    }
}
