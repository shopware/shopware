<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1646817331AddCmsClassColumnCmsPage;

class Migration1646817331AddCmsClassColumnCmsPageTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1646817331AddCmsClassColumnCmsPage();
        $resultColumnExists = $this->hasColumn('cms_page', 'css_class');
        static::assertFalse($resultColumnExists);

        $migration->update($this->connection);

        $resultColumnExists = $this->hasColumn('cms_page', 'css_class');
        static::assertTrue($resultColumnExists);
    }

    private function prepare(): void
    {
        $resultColumnExists = $this->hasColumn('cms_page', 'css_class');

        if ($resultColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `cms_page` DROP COLUMN `css_class`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column) use ($columnName): bool {
                return $column->getName() === $columnName;
            }
        )) > 0;
    }
}
