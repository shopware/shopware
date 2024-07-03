<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1720000172AddMaxCharacterCountToProductSearchConfiguration;

/**
 * @internal
 */
#[CoversClass(Migration1720000172AddMaxCharacterCountToProductSearchConfiguration::class)]
#[Package('core')]
class Migration1720000172AddMaxCharacterCountToProductSearchConfigurationTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns('product_search_config');

        static::assertArrayHasKey('max_character_count', $columns);
        static::assertTrue($columns['max_character_count']->getNotnull());
        static::assertSame('60', $columns['max_character_count']->getDefault());
    }

    private function migrate(): void
    {
        (new Migration1720000172AddMaxCharacterCountToProductSearchConfiguration())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product_search_config` DROP COLUMN `max_character_count`');
    }
}
