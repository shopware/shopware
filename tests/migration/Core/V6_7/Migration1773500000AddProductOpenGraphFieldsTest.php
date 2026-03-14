<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773500000AddProductOpenGraphFields;

/**
 * @internal
 */
#[CoversClass(Migration1773500000AddProductOpenGraphFields::class)]
class Migration1773500000AddProductOpenGraphFieldsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testColumnsAndForeignKeyAreCreated(): void
    {
        $migration = new Migration1773500000AddProductOpenGraphFields();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'open_graph_media_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product_translation', 'og_title'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product_translation', 'og_description'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'fk.product.open_graph_media_id'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1773500000AddProductOpenGraphFields();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'open_graph_media_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product_translation', 'og_title'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'product_translation', 'og_description'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'fk.product.open_graph_media_id'));
    }
}
