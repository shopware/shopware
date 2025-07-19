<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1735807464AddCustomFieldStoreApiAware;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1735807464AddCustomFieldStoreApiAware::class)]
class Migration1735807464AddCustomFieldStoreApiAwareTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testCustomFieldHasStoreApiAwareColumn(): void
    {
        $this->rollback();
        $this->executeMigration();
        $this->executeMigration();
        $columns = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `custom_field`');
        $columnNames = array_column($columns, 'Field');

        $storeApiAwareColumnKey = array_search('store_api_aware', $columnNames, true);

        if ($storeApiAwareColumnKey === false) {
            static::fail('Column "store_api_aware" not found in "custom_field" table');
        }

        static::assertSame('store_api_aware', $columns[$storeApiAwareColumnKey]['Field']);
        static::assertSame('tinyint(1)', $columns[$storeApiAwareColumnKey]['Type']);
        static::assertSame('NO', $columns[$storeApiAwareColumnKey]['Null']);
        static::assertSame('1', $columns[$storeApiAwareColumnKey]['Default']);
    }

    private function executeMigration(): void
    {
        (new Migration1735807464AddCustomFieldStoreApiAware())->update($this->connection);
    }

    private function rollback(): void
    {
        $columns = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `custom_field`');
        $columnNames = array_column($columns, 'Field');

        $storeApiAwareColumnKey = array_search('store_api_aware', $columnNames, true);

        if ($storeApiAwareColumnKey === false) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `custom_field` DROP COLUMN `store_api_aware`');
    }
}
