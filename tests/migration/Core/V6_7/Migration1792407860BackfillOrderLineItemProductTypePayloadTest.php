<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1792407860BackfillOrderLineItemProductTypePayload;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1792407860BackfillOrderLineItemProductTypePayload::class)]
class Migration1792407860BackfillOrderLineItemProductTypePayloadTest extends TestCase
{
    use MigrationTestTrait;

    private static bool $statesColumnAdded = false;

    private Connection $connection;

    private string $orderId;

    public static function setUpBeforeClass(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if (TableHelper::columnExists($connection, 'order_line_item', 'states')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `order_line_item` ADD COLUMN `states` JSON NULL');
        self::$statesColumnAdded = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$statesColumnAdded) {
            return;
        }

        KernelLifecycleManager::getConnection()->executeStatement('ALTER TABLE `order_line_item` DROP COLUMN `states`');
        self::$statesColumnAdded = false;
    }

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->orderId = Uuid::randomBytes();

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792407860, (new Migration1792407860BackfillOrderLineItemProductTypePayload())->getCreationTimestamp());
    }

    public function testFillsProductTypeOfEveryLineItemWithoutOne(): void
    {
        $this->insertLineItems(1500, type: 'product', states: ['is-download']);
        $this->insertLineItems(1500, type: 'product', states: ['is-physical']);
        $this->insertLineItems(10, type: 'custom', states: ['is-download']);
        $this->insertLineItems(10, type: 'custom', states: ['is-physical']);

        $migration = new Migration1792407860BackfillOrderLineItemProductTypePayload();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame([
            ['type' => 'custom', 'state' => 'is-download', 'productType' => 'digital', 'count' => 10],
            ['type' => 'custom', 'state' => 'is-physical', 'productType' => 'physical', 'count' => 10],
            ['type' => 'product', 'state' => 'is-download', 'productType' => 'digital', 'count' => 1500],
            ['type' => 'product', 'state' => 'is-physical', 'productType' => 'physical', 'count' => 1500],
        ], $this->fetchProductTypes());
        static::assertSame(3020, $this->countLineItemsWithProductNumber());
    }

    public function testKeepsProductTypeThatIsAlreadySet(): void
    {
        $this->insertLineItems(1, type: 'product', states: ['is-download'], payload: ['productType' => 'digital']);
        $this->insertLineItems(1, type: 'product', states: ['is-physical'], payload: ['productType' => 'subscription']);

        (new Migration1792407860BackfillOrderLineItemProductTypePayload())->update($this->connection);

        static::assertSame([
            ['type' => 'product', 'state' => 'is-download', 'productType' => 'digital', 'count' => 1],
            ['type' => 'product', 'state' => 'is-physical', 'productType' => 'subscription', 'count' => 1],
        ], $this->fetchProductTypes());
    }

    public function testLeavesLineItemsWithoutProductStateUntouched(): void
    {
        $this->insertLineItems(1, type: 'promotion', states: ['is-physical']);
        $this->insertLineItems(1, type: 'product', states: []);
        $this->insertLineItems(1, type: 'product', states: null);

        (new Migration1792407860BackfillOrderLineItemProductTypePayload())->update($this->connection);

        static::assertSame([
            ['type' => 'product', 'state' => null, 'productType' => null, 'count' => 2],
            ['type' => 'promotion', 'state' => 'is-physical', 'productType' => null, 'count' => 1],
        ], $this->fetchProductTypes());
    }

    public function testFillsEveryVersionOfALineItem(): void
    {
        $lineItemId = Uuid::randomBytes();

        $queue = new MultiInsertQueryQueue($this->connection);
        $queue->addInsert('order_line_item', $this->lineItem($lineItemId, Defaults::LIVE_VERSION, type: 'product', states: ['is-download']));
        $queue->addInsert('order_line_item', $this->lineItem($lineItemId, Uuid::randomHex(), type: 'product', states: ['is-download']));
        $queue->execute();

        (new Migration1792407860BackfillOrderLineItemProductTypePayload())->update($this->connection);

        static::assertSame([
            ['type' => 'product', 'state' => 'is-download', 'productType' => 'digital', 'count' => 2],
        ], $this->fetchProductTypes());
    }

    /**
     * @param list<string>|null $states
     * @param array<string, string> $payload
     */
    private function insertLineItems(int $count, string $type, ?array $states, array $payload = ['productNumber' => 'SW10000']): void
    {
        $queue = new MultiInsertQueryQueue($this->connection, 500);

        for ($i = 0; $i < $count; ++$i) {
            $queue->addInsert('order_line_item', $this->lineItem(Uuid::randomBytes(), Defaults::LIVE_VERSION, $type, $states, $payload));
        }

        $queue->execute();
    }

    /**
     * @param list<string>|null $states
     * @param array<string, string> $payload
     *
     * @return array<string, mixed>
     */
    private function lineItem(string $id, string $versionId, string $type, ?array $states, array $payload = ['productNumber' => 'SW10000']): array
    {
        return [
            'id' => $id,
            'version_id' => Uuid::fromHexToBytes($versionId),
            'order_id' => $this->orderId,
            'order_version_id' => Uuid::fromHexToBytes($versionId),
            'identifier' => Uuid::fromBytesToHex($id),
            'label' => 'Line item',
            'quantity' => 1,
            'type' => $type,
            'payload' => Json::encode($payload),
            'states' => $states === null ? null : Json::encode($states),
            'price' => Json::encode(['unitPrice' => 10.0, 'totalPrice' => 10.0, 'quantity' => 1, 'calculatedTaxes' => [], 'taxRules' => []]),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    /**
     * @return list<array{type: string, state: string|null, productType: string|null, count: int}>
     */
    private function fetchProductTypes(): array
    {
        /** @var list<array{type: string, state: string|null, productType: string|null, count: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `type`,
                JSON_UNQUOTE(JSON_EXTRACT(`states`, \'$[0]\')) AS `state`,
                JSON_UNQUOTE(JSON_EXTRACT(`payload`, \'$.productType\')) AS `productType`,
                COUNT(*) AS `count`
            FROM `order_line_item`
            WHERE `order_id` = :orderId
            GROUP BY `type`, `state`, `productType`
            ORDER BY `type`, `state`, `productType`',
            ['orderId' => $this->orderId]
        );

        return array_map(static fn (array $row): array => [...$row, 'count' => (int) $row['count']], $rows);
    }

    private function countLineItemsWithProductNumber(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `order_line_item` WHERE `order_id` = :orderId AND JSON_CONTAINS_PATH(`payload`, \'one\', \'$.productNumber\')',
            ['orderId' => $this->orderId]
        );
    }
}
