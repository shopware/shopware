<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1702982372FixProductCrossSellingSortByPrice;

/**
 * @internal
 */
#[CoversClass(Migration1702982372FixProductCrossSellingSortByPrice::class)]
class Migration1702982372FixProductCrossSellingSortByPriceTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1702982372', (new Migration1702982372FixProductCrossSellingSortByPrice())->getCreationTimestamp());
    }

    public function testMigrationChangesSortBy(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $productId = Uuid::randomBytes();
        $connection->insert('product', [
            'id' => $productId,
            'stock' => 1,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        $crossSellingId = Uuid::randomBytes();
        $connection->insert('product_cross_selling', [
            'id' => $crossSellingId,
            'product_id' => $productId,
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'position' => 0,
            'type' => 'productStream',
            'sort_by' => 'price',
            'sort_direction' => FieldSorting::ASCENDING,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1702982372FixProductCrossSellingSortByPrice();
        $migration->update($connection);

        $sortBy = $connection->executeQuery('
        SELECT `sort_by` from `product_cross_selling` WHERE `id` = :id
        ', ['id' => $crossSellingId])->fetchOne();

        static::assertEquals('cheapestPrice', $sortBy);
    }
}
