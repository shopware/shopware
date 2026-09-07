<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxRuleCollectionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxRuleCollectionFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1788524746CreateOrderPriceModification;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1788524746CreateOrderPriceModification::class)]
class Migration1788524746CreateOrderPriceModificationTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $createdOrderIds = [];

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        // Every test starts from a known-good, freshly (re-)created table, exactly as if the
        // migration had just run on an installation that never had it.
        $this->connection->executeStatement('DROP TABLE IF EXISTS `order_price_modification`');
        (new Migration1788524746CreateOrderPriceModification())->update($this->connection);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdOrderIds as $orderId) {
            $this->connection->executeStatement(
                'DELETE FROM `order` WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($orderId)]
            );
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788524746, (new Migration1788524746CreateOrderPriceModification())->getCreationTimestamp());
    }

    public function testMigrationCreatesTheTableIdempotently(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `order_price_modification`');
        static::assertFalse(TableHelper::tableExists($this->connection, 'order_price_modification'));

        $migration = new Migration1788524746CreateOrderPriceModification();
        // A migration must tolerate being run more than once (e.g. a retried post-update).
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'order_price_modification'));
    }

    public function testTableHasExpectedColumnsAndNullability(): void
    {
        foreach (['id', 'version_id', 'order_id', 'order_version_id', 'label', 'price', 'position', 'created_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'order_price_modification', $column), "Column `{$column}` should exist");
        }

        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'order_price_modification', 'label')->isNotNull);
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'order_price_modification', 'price')->isNotNull);
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'order_price_modification', 'order_id')->isNotNull);

        // Everything a manually admin-added row (no plugin, no structured tax treatment) leaves
        // empty must be nullable, not just absent from the write payload.
        foreach (['description', 'price_definition', 'tax_rules', 'type', 'referenced_id', 'payload', 'updated_at'] as $column) {
            static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'order_price_modification', $column)->isNotNull, "Column `{$column}` should be nullable");
        }
    }

    public function testForeignKeyToOrderIsDeclaredCascade(): void
    {
        $foreignKey = TableHelper::getForeignKeyOfTable($this->connection, 'order_price_modification', 'fk.order_price_modification.order_id');

        static::assertSame(['order_id', 'order_version_id'], $foreignKey->referencingColumnNames);
        static::assertSame('order', $foreignKey->referencedTableName);
        static::assertSame(['id', 'version_id'], $foreignKey->referencedColumnNames);
        static::assertSame('CASCADE', $foreignKey->onDeleteAction);
    }

    public function testDeletingTheOrderCascadesToItsPriceModifications(): void
    {
        $orderId = $this->createOrder();
        $modificationId = $this->createPriceModification($orderId);

        static::assertSame(1, (int) $this->countPriceModification($modificationId));

        $this->connection->executeStatement(
            'DELETE FROM `order` WHERE id = :orderId',
            ['orderId' => Uuid::fromHexToBytes($orderId)]
        );

        static::assertSame(0, (int) $this->countPriceModification($modificationId));
    }

    /**
     * Proves the round trip through the real serializer, not just that the JSON column accepts
     * the value: OrderPriceModificationTaxLockValidator's write-lock depends on this serializer's
     * output staying byte-stable (see TaxRuleCollectionFieldSerializer::encode()), so the shape it
     * decodes back into matters as much as the storage itself.
     */
    public function testTaxRulesColumnRoundTripsThroughTheRealSerializer(): void
    {
        $orderId = $this->createOrder();
        $modificationId = $this->createPriceModification(
            $orderId,
            taxRulesJson: '[{"taxRate":19.0,"percentage":100.0},{"taxRate":7.0,"percentage":100.0}]'
        );

        $storedJson = $this->connection->fetchOne(
            'SELECT tax_rules FROM `order_price_modification` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($modificationId)]
        );
        static::assertIsString($storedJson);

        $serializer = static::getContainer()->get(TaxRuleCollectionFieldSerializer::class);
        $decoded = $serializer->decode(new TaxRuleCollectionField('tax_rules', 'taxRules'), $storedJson);

        static::assertInstanceOf(TaxRuleCollection::class, $decoded);
        static::assertCount(2, $decoded);
        static::assertEqualsCanonicalizing(
            [19.0, 7.0],
            array_values(array_map(static fn ($rule) => $rule->getTaxRate(), $decoded->getElements()))
        );
    }

    public function testTaxRulesColumnAcceptsNullForATaxExemptModification(): void
    {
        $orderId = $this->createOrder();
        $modificationId = $this->createPriceModification($orderId, taxRulesJson: null);

        $storedJson = $this->connection->fetchOne(
            'SELECT tax_rules FROM `order_price_modification` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($modificationId)]
        );

        static::assertNull($storedJson);
    }

    private function countPriceModification(string $modificationId): string|int
    {
        return $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `order_price_modification` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($modificationId)]
        );
    }

    private function createOrder(): string
    {
        $orderId = Uuid::randomHex();
        $this->createdOrderIds[] = $orderId;

        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO `order` SET
                id = :orderId,
                version_id = :defaultVersion,
                state_id = (SELECT `initial_state_id` FROM `state_machine` WHERE `technical_name` = 'order.state'),
                order_number = :orderNumber,
                currency_id = :defaultCurrency,
                language_id = :defaultLanguage,
                sales_channel_id = :defaultSalesChannel,
                billing_address_id = :billingAddressId,
                billing_address_version_id = :defaultVersion,
                price = '{}',
                order_date_time = NOW(),
                shipping_costs = '{}',
                created_at = NOW();
        SQL, [
            'orderId' => Uuid::fromHexToBytes($orderId),
            'orderNumber' => Uuid::randomHex(),
            'defaultVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'defaultCurrency' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'defaultLanguage' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'defaultSalesChannel' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billingAddressId' => Uuid::randomBytes(),
        ]);

        return $orderId;
    }

    private function createPriceModification(string $orderId, ?string $taxRulesJson = null): string
    {
        $modificationId = Uuid::randomHex();

        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO `order_price_modification` SET
                id = :id,
                version_id = :defaultVersion,
                order_id = :orderId,
                order_version_id = :defaultVersion,
                label = 'Goodwill discount',
                price = -10.0,
                tax_rules = :taxRules,
                created_at = NOW();
        SQL, [
            'id' => Uuid::fromHexToBytes($modificationId),
            'defaultVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'orderId' => Uuid::fromHexToBytes($orderId),
            'taxRules' => $taxRulesJson,
        ]);

        return $modificationId;
    }
}
