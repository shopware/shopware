<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1783666808AddAppFeatureTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1783666808AddAppFeatureTable::class)]
class Migration1783666808AddAppFeatureTableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1783666808, (new Migration1783666808AddAppFeatureTable())->getCreationTimestamp());
    }

    public function testUpdateCreatesAppFeatureTableIdempotently(): void
    {
        $migration = new Migration1783666808AddAppFeatureTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['id', 'app_id', 'app_name', 'type', 'name', 'payload', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'app_feature', $column));
        }
    }
}
