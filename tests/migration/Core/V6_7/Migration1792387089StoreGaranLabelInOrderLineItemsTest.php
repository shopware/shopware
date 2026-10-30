<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1783944800AddGaranLabel;
use Shopware\Core\Migration\V6_7\Migration1792387089StoreGaranLabelInOrderLineItems;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1792387089StoreGaranLabelInOrderLineItems::class)]
class Migration1792387089StoreGaranLabelInOrderLineItemsTest extends TestCase
{
    use MigrationTestTrait;

    private const ORDER_LANGUAGE = 'order';

    private const PARENT_LANGUAGE = 'parent';

    private const SYSTEM_LANGUAGE = 'system';

    private const LABEL = ['guaranteeMonths' => 36, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-36'];

    private const STORED_LABEL = ['guaranteeMonths' => 42, 'brand' => 'Old', 'modelIdentifier' => 'OLD-42'];

    private const PRODUCT = ['guarantee_confirmed' => 1, 'guarantee_months' => 36, 'manufacturer_number' => 'ACME-36'];

    private Connection $connection;

    private string $liveVersionId;

    /**
     * @var array<string, string>
     */
    private array $languageIds;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $this->languageIds = [
            self::ORDER_LANGUAGE => Uuid::randomBytes(),
            self::PARENT_LANGUAGE => Uuid::randomBytes(),
            self::SYSTEM_LANGUAGE => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ];

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792387089, (new Migration1792387089StoreGaranLabelInOrderLineItems())->getCreationTimestamp());
    }

    /**
     * @return \Generator<string, array{array<string, int|string|null>|null, array<string, int|string|null>|null, array<string, string>, array<string, mixed>, array<string, int|string>|false|null}>
     */
    public static function lineItemProvider(): \Generator
    {
        $brand = [self::ORDER_LANGUAGE => 'ACME'];

        yield 'a product with a confirmed guarantee stores its label' => [self::PRODUCT, null, $brand, [], self::LABEL];
        yield 'brand and model identifier are trimmed' => [['manufacturer_number' => ' ACME-36 '] + self::PRODUCT, null, [self::ORDER_LANGUAGE => ' ACME '], [], self::LABEL];
        yield 'a variant inherits the label of its parent' => [['guarantee_confirmed' => null, 'guarantee_months' => null, 'manufacturer_number' => null], self::PRODUCT, $brand, [], self::LABEL];
        yield 'a variant without confirmation of its own has no label' => [['guarantee_confirmed' => 0] + self::PRODUCT, self::PRODUCT, $brand, [], false];
        yield 'the brand falls back to the parent language' => [self::PRODUCT, null, [self::PARENT_LANGUAGE => 'ACME', self::SYSTEM_LANGUAGE => 'System'], [], self::LABEL];
        yield 'the brand falls back to the system language' => [self::PRODUCT, null, [self::SYSTEM_LANGUAGE => 'ACME'], [], self::LABEL];
        yield 'an unconfirmed guarantee has no label' => [['guarantee_confirmed' => 0] + self::PRODUCT, null, $brand, [], false];
        yield 'a guarantee without label duration has no label' => [['guarantee_months' => 24] + self::PRODUCT, null, $brand, [], false];
        yield 'a product without brand has no label' => [self::PRODUCT, null, [self::ORDER_LANGUAGE => ' '], [], false];
        yield 'a product without model identifier has no label' => [['manufacturer_number' => ''] + self::PRODUCT, null, $brand, [], false];
        yield 'a deleted product has no label' => [null, null, $brand, [], false];
        yield 'a stored label is kept' => [self::PRODUCT, null, $brand, ['garanLabel' => self::STORED_LABEL], self::STORED_LABEL];
        yield 'a stored null is kept' => [self::PRODUCT, null, $brand, ['garanLabel' => null], null];
    }

    /**
     * @param array<string, int|string|null>|null $product null for a deleted product
     * @param array<string, int|string|null>|null $parent
     * @param array<string, string> $manufacturerNames
     * @param array<string, mixed> $payload
     * @param array<string, int|string>|false|null $expected false for a payload without label
     */
    #[DataProvider('lineItemProvider')]
    public function testStoresTheLabelOfTheProduct(?array $product, ?array $parent, array $manufacturerNames, array $payload, array|false|null $expected): void
    {
        $productId = $this->insertProduct($product, $parent, $manufacturerNames);
        $lineItemId = $this->insertLineItems($productId, 1, $payload)[0];

        $migration = new Migration1792387089StoreGaranLabelInOrderLineItems();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $payload = $this->fetchPayloads([$lineItemId])[0];

        if ($expected === false) {
            static::assertArrayNotHasKey('garanLabel', $payload);

            return;
        }

        static::assertArrayHasKey('garanLabel', $payload);
        static::assertSame(self::sortKeys($expected), self::sortKeys($payload['garanLabel']));
        static::assertSame('product payload', $payload['other'] ?? null, 'the rest of the payload is kept');
    }

    /**
     * a full last batch is followed by a fetch that finds no more line items
     */
    #[TestWith([1000])]
    #[TestWith([1001])]
    public function testStoresTheLabelOfLineItemsInBatches(int $count): void
    {
        $productId = $this->insertProduct(self::PRODUCT, null, [self::ORDER_LANGUAGE => 'ACME']);
        $lineItemIds = $this->insertLineItems($productId, $count, []);

        (new Migration1792387089StoreGaranLabelInOrderLineItems())->update($this->connection);

        foreach ($this->fetchPayloads($lineItemIds) as $payload) {
            static::assertArrayHasKey('garanLabel', $payload);
        }
    }

    public function testSkipsLineItemsCreatedBeforeTheGaranFieldsWereAdded(): void
    {
        // a 6.6 shop added the fields with the backported migration, it records the 6.7 one when it updates
        $this->connection->executeStatement(
            'INSERT INTO `migration` (`class`, `creation_timestamp`, `update`) VALUES (:class, 1783944800, \'2026-07-01 00:00:00.000000\')',
            ['class' => 'Shopware\Core\Migration\V6_6\Migration1783944800AddGaranLabel']
        );
        $this->connection->executeStatement(
            'UPDATE `migration` SET `update` = \'2026-09-01 00:00:00.000000\' WHERE `class` = :class',
            ['class' => Migration1783944800AddGaranLabel::class]
        );

        $productId = $this->insertProduct(self::PRODUCT, null, [self::ORDER_LANGUAGE => 'ACME']);
        [$beforeId, $sinceId] = $this->insertLineItems($productId, 2, []);
        $this->connection->executeStatement(
            'UPDATE `order_line_item` SET `created_at` = IF(`id` = :beforeId, \'2026-06-30 23:59:59.999\', \'2026-07-01 00:00:00.000\') WHERE `id` IN (:beforeId, :sinceId)',
            ['beforeId' => $beforeId, 'sinceId' => $sinceId]
        );

        (new Migration1792387089StoreGaranLabelInOrderLineItems())->update($this->connection);

        static::assertArrayNotHasKey('garanLabel', $this->fetchPayloads([$beforeId])[0]);
        static::assertArrayHasKey('garanLabel', $this->fetchPayloads([$sinceId])[0]);
    }

    /**
     * @param array<string, int|string|null>|null $product
     * @param array<string, int|string|null>|null $parent
     * @param array<string, string> $manufacturerNames
     */
    private function insertProduct(?array $product, ?array $parent, array $manufacturerNames): string
    {
        $productId = Uuid::randomBytes();
        $parentId = $parent !== null ? Uuid::randomBytes() : null;
        $manufacturerId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($manufacturerNames as $language => $name) {
            $this->connection->insert('product_manufacturer_translation', [
                'product_manufacturer_id' => $manufacturerId,
                'product_manufacturer_version_id' => $this->liveVersionId,
                'language_id' => $this->languageIds[$language],
                'name' => $name,
                'created_at' => $now,
            ]);
        }

        // the manufacturer is inherited from the parent, if there is one
        if ($parent !== null) {
            $this->connection->insert('product', $parent + [
                'id' => $parentId,
                'version_id' => $this->liveVersionId,
                'product_manufacturer_id' => $manufacturerId,
                'product_manufacturer_version_id' => $this->liveVersionId,
                'stock' => 1,
                'created_at' => $now,
            ]);
        }

        if ($product !== null) {
            $this->connection->insert('product', $product + [
                'id' => $productId,
                'version_id' => $this->liveVersionId,
                'parent_id' => $parentId,
                'parent_version_id' => $parentId !== null ? $this->liveVersionId : null,
                'product_manufacturer_id' => $parentId === null ? $manufacturerId : null,
                'product_manufacturer_version_id' => $parentId === null ? $this->liveVersionId : null,
                'stock' => 1,
                'created_at' => $now,
            ]);
        }

        return $productId;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function insertLineItems(string $productId, int $count, array $payload): array
    {
        $orderId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('language', [
            'id' => $this->languageIds[self::ORDER_LANGUAGE],
            'parent_id' => $this->languageIds[self::PARENT_LANGUAGE],
            'locale_id' => Uuid::randomBytes(),
            'name' => 'Order language',
            'created_at' => $now,
        ]);

        $this->connection->insert('`order`', [
            'id' => $orderId,
            'version_id' => $this->liveVersionId,
            'state_id' => Uuid::randomBytes(),
            'currency_id' => Uuid::randomBytes(),
            'language_id' => $this->languageIds[self::ORDER_LANGUAGE],
            'sales_channel_id' => Uuid::randomBytes(),
            'billing_address_id' => Uuid::randomBytes(),
            'billing_address_version_id' => $this->liveVersionId,
            'price' => '{}',
            'shipping_costs' => '{}',
            'order_date_time' => $now,
            'created_at' => $now,
        ]);

        $lineItemIds = [];
        $queue = new MultiInsertQueryQueue($this->connection, $count);

        for ($i = 0; $i < $count; ++$i) {
            $lineItemId = Uuid::randomBytes();
            $lineItemIds[] = $lineItemId;

            $queue->addInsert('order_line_item', [
                'id' => $lineItemId,
                'version_id' => $this->liveVersionId,
                'order_id' => $orderId,
                'order_version_id' => $this->liveVersionId,
                'product_id' => $productId,
                'product_version_id' => $this->liveVersionId,
                'identifier' => Uuid::randomHex(),
                'label' => 'GARAN product',
                'quantity' => 1,
                'type' => 'product',
                'payload' => Json::encode(['other' => 'product payload'] + $payload),
                'price' => '{}',
                'created_at' => $now,
            ]);
        }

        $queue->execute();

        return $lineItemIds;
    }

    /**
     * @param list<string> $lineItemIds
     *
     * @return list<array<string, mixed>>
     */
    private function fetchPayloads(array $lineItemIds): array
    {
        $payloads = $this->connection->fetchFirstColumn(
            'SELECT `payload` FROM `order_line_item` WHERE `id` IN (:ids)',
            ['ids' => $lineItemIds],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertCount(\count($lineItemIds), $payloads);

        return array_map(static fn (string $payload): array => Json::decodeToArray($payload), $payloads);
    }

    /**
     * MySQL reorders the keys of JSON objects
     *
     * @param array<string, mixed>|null $label
     *
     * @return array<string, mixed>|null
     */
    private static function sortKeys(?array $label): ?array
    {
        if ($label !== null) {
            ksort($label);
        }

        return $label;
    }
}
