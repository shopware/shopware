<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1726049442UpdateVariantListingConfigInProductTable;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1726049442UpdateVariantListingConfigInProductTable::class)]
class Migration1726049442UpdateVariantListingConfigInProductTableTest extends TestCase
{
    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->ids = new IdsCollection();

        try {
            $this->connection->executeStatement(
                'DELETE FROM `product`'
            );
        } catch (\Throwable) {
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM `product`'
            );
        } catch (\Throwable) {
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1726049442, (new Migration1726049442UpdateVariantListingConfigInProductTable())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->createProducts();

        $migration = new Migration1726049442UpdateVariantListingConfigInProductTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $expectedProducts = [
            [
                'id' => $this->ids->get('product-1'),
                'parent_id' => null,
                'variant_listing_config' => [
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('product-4'),
                    'configuratorGroupConfig' => [],
                ],
            ],
            [
                'id' => $this->ids->get('product-2'),
                'parent_id' => null,
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->get('product-3'),
                'parent_id' => null,
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->get('product-4'),
                'parent_id' => $this->ids->get('product-1'),
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->get('product-5'),
                'parent_id' => $this->ids->get('product-3'),
                'variant_listing_config' => null,
            ],
        ];

        $products = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, LOWER(HEX(parent_id)) as parent_id, variant_listing_config FROM product ORDER BY product_number ASC'
        );

        foreach ($products as &$product) {
            $product['variant_listing_config'] = $product['variant_listing_config'] ? json_decode($product['variant_listing_config'], true) : null;
        }

        static::assertSame($expectedProducts, $products);
    }

    private function createProducts(): void
    {
        $products = [
            [
                'id' => $this->ids->getBytes('product-1'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => null,
                'product_number' => 'product 1',
                'stock' => 10,
                'variant_listing_config' => json_encode([
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('product-4'),
                    'configuratorGroupConfig' => [],
                ]),
            ],
            [
                'id' => $this->ids->getBytes('product-2'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => null,
                'product_number' => 'product 2',
                'stock' => 10,
                'variant_listing_config' => json_encode([
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('product-6'),
                    'configuratorGroupConfig' => [],
                ]),
            ],
            [
                'id' => $this->ids->getBytes('product-3'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => null,
                'product_number' => 'product 3',
                'stock' => 10,
                'variant_listing_config' => json_encode([
                    'displayParent' => 0,
                    'mainVariantId' => $this->ids->get('product-4'),
                    'configuratorGroupConfig' => [],
                ]),
            ],
            [
                'id' => $this->ids->getBytes('product-4'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => $this->ids->getBytes('product-1'),
                'product_number' => 'product 4',
                'stock' => 10,
                'variant_listing_config' => null,
            ],
            [
                'id' => $this->ids->getBytes('product-5'),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'parent_id' => $this->ids->getBytes('product-3'),
                'product_number' => 'product 5',
                'stock' => 10,
                'variant_listing_config' => null,
            ],
        ];

        foreach ($products as $product) {
            $this->connection->insert('product', $product);
        }
    }
}
