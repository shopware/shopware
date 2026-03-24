<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1774359918ProductPriceQuantityRangeMinValues;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(Migration1774359918ProductPriceQuantityRangeMinValues::class)]
class Migration1774359918ProductPriceQuantityRangeMinValuesTest extends TestCase
{
    private Connection $connection;

    private string $productId;

    private string $ruleId;

    private string $rule2Id;

    private string $productPriceId;

    private string $productPrice2Id;

    private string $productPrice3Id;

    private string $versionId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->productId = Uuid::randomBytes();
        $this->ruleId = Uuid::randomBytes();
        $this->rule2Id = Uuid::randomBytes();
        $this->productPriceId = Uuid::randomBytes();
        $this->productPrice2Id = Uuid::randomBytes();
        $this->productPrice3Id = Uuid::randomBytes();
        $this->versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
    }

    protected function tearDown(): void
    {
        $this->connection->delete('product_price', ['id' => $this->productPriceId]);
        $this->connection->delete('product_price', ['id' => $this->productPrice2Id]);
        $this->connection->delete('product_price', ['id' => $this->productPrice3Id]);
        $this->connection->delete('rule', ['id' => $this->ruleId]);
        $this->connection->delete('rule', ['id' => $this->rule2Id]);
        $this->connection->delete('product', ['id' => $this->productId]);
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1774359918ProductPriceQuantityRangeMinValues();

        static::assertSame(1774359918, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->connection->insert('product', [
            'id' => $this->productId,
            'version_id' => $this->versionId,
            'stock' => 10,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule', [
            'id' => $this->ruleId,
            'name' => 'Price rule 1',
            'priority' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('rule', [
            'id' => $this->rule2Id,
            'name' => 'Price rule 2',
            'priority' => 2,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('product_price', [
            'id' => $this->productPriceId,
            'version_id' => $this->versionId,
            'rule_id' => $this->ruleId,
            'product_id' => $this->productId,
            'product_version_id' => $this->versionId,
            'price' => json_encode([
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 9,
                    'linked' => false,
                ],
            ], \JSON_THROW_ON_ERROR),
            'quantity_start' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('product_price', [
            'id' => $this->productPrice2Id,
            'version_id' => $this->versionId,
            'rule_id' => $this->rule2Id,
            'product_id' => $this->productId,
            'product_version_id' => $this->versionId,
            'price' => json_encode([
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 20,
                    'net' => 18,
                    'linked' => false,
                ],
            ], \JSON_THROW_ON_ERROR),
            'quantity_start' => -1,
            'quantity_end' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('product_price', [
            'id' => $this->productPrice3Id,
            'version_id' => $this->versionId,
            'rule_id' => $this->rule2Id,
            'product_id' => $this->productId,
            'product_version_id' => $this->versionId,
            'price' => json_encode([
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 30,
                    'net' => 27,
                    'linked' => false,
                ],
            ], \JSON_THROW_ON_ERROR),
            'quantity_start' => 2,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1774359918ProductPriceQuantityRangeMinValues();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $sql = 'SELECT `quantity_start`, `quantity_end` FROM `product_price` WHERE `id` = :id';

        $price = $this->connection->fetchAssociative($sql, ['id' => $this->productPriceId]);

        static::assertIsArray($price);
        static::assertSame('1', $price['quantity_start']);
        static::assertNull($price['quantity_end']);

        $price2 = $this->connection->fetchAssociative($sql, ['id' => $this->productPrice2Id]);

        static::assertIsArray($price2);
        static::assertSame('1', $price2['quantity_start']);
        static::assertSame('1', $price2['quantity_end']);

        $price3 = $this->connection->fetchAssociative($sql, ['id' => $this->productPrice3Id]);

        static::assertIsArray($price3);
        static::assertSame('2', $price3['quantity_start']);
        static::assertNull($price3['quantity_end']);
    }
}
