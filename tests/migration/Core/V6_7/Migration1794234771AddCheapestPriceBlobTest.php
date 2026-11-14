<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1794234771AddCheapestPriceBlob;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794234771AddCheapestPriceBlob::class)]
class Migration1794234771AddCheapestPriceBlobTest extends TestCase
{
    public function testAddsNullableBlobWithoutChangingLegacyColumn(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $storedPrices = [];
        if (TableHelper::columnExists($connection, 'product', 'cheapest_price_blob')) {
            $storedPrices = $connection->fetchAllAssociative('SELECT id, version_id, cheapest_price_blob FROM product WHERE cheapest_price_blob IS NOT NULL');
            $connection->executeStatement('ALTER TABLE product DROP COLUMN cheapest_price_blob');
        }

        $migration = new Migration1794234771AddCheapestPriceBlob();
        try {
            $migration->update($connection);
            $migration->update($connection);

            $column = $connection->fetchAssociative('SHOW COLUMNS FROM product WHERE Field = \'cheapest_price_blob\'');
            static::assertIsArray($column);
            static::assertSame('longblob', $column['Type']);
            static::assertSame('YES', $column['Null']);
            static::assertTrue(TableHelper::columnExists($connection, 'product', 'cheapest_price'));
        } finally {
            $migration->update($connection);
            foreach ($storedPrices as $row) {
                $connection->update('product', ['cheapest_price_blob' => $row['cheapest_price_blob']], ['id' => $row['id'], 'version_id' => $row['version_id']]);
            }
        }
    }
}
