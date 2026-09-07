<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
class StockStorageConcurrencyTest extends TestCase
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private Connection $firstConnection;

    private Connection $secondConnection;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->productRepository = static::getContainer()->get('product.repository');

        $taxId = $this->getValidTaxId();
        $price = [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.4, 'linked' => false]];
        $parent = [
            'id' => $this->ids->get('stock-lock-parent'),
            'productNumber' => $this->ids->get('stock-lock-parent-number'),
            'name' => 'Stock lock parent',
            'type' => ProductDefinition::TYPE_PHYSICAL,
            'taxId' => $taxId,
            'price' => $price,
            'stock' => 10,
            'isCloseout' => true,
            'minPurchase' => 1,
        ];
        $firstVariant = [
            'id' => $this->ids->get('stock-lock-first-variant'),
            'parentId' => $this->ids->get('stock-lock-parent'),
            'productNumber' => $this->ids->get('stock-lock-first-variant-number'),
            'name' => 'Stock lock first variant',
            'type' => ProductDefinition::TYPE_PHYSICAL,
            'taxId' => $taxId,
            'price' => $price,
            'stock' => 3,
            'isCloseout' => null,
            'minPurchase' => null,
        ];
        $secondVariant = [
            'id' => $this->ids->get('stock-lock-second-variant'),
            'parentId' => $this->ids->get('stock-lock-parent'),
            'productNumber' => $this->ids->get('stock-lock-second-variant-number'),
            'name' => 'Stock lock second variant',
            'type' => ProductDefinition::TYPE_PHYSICAL,
            'taxId' => $taxId,
            'price' => $price,
            'stock' => 3,
            'isCloseout' => null,
            'minPurchase' => null,
        ];

        $this->productRepository->create([$parent], $this->context);
        $this->productRepository->create([$firstVariant, $secondVariant], $this->context);

        $this->firstConnection = $this->createConnection();
        $this->secondConnection = $this->createConnection();
        $this->firstConnection->setTransactionIsolation(TransactionIsolationLevel::REPEATABLE_READ);
        $this->secondConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
    }

    protected function tearDown(): void
    {
        $this->secondConnection->close();
        $this->firstConnection->close();

        $this->productRepository->delete([
            ['id' => $this->ids->get('stock-lock-first-variant')],
            ['id' => $this->ids->get('stock-lock-second-variant')],
        ], $this->context);
        $this->productRepository->delete([['id' => $this->ids->get('stock-lock-parent')]], $this->context);
    }

    public function testStandaloneVariantAvailabilityDoesNotWaitForLockedParent(): void
    {
        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $this->firstConnection->beginTransaction();
        (new StockStorage($this->firstConnection, $dispatcher))->index([$this->ids->get('stock-lock-first-variant')], $this->context);
        $this->firstConnection->executeStatement(
            'SELECT id FROM product WHERE id = :id AND version_id = :version FOR UPDATE',
            [
                'id' => $this->ids->getBytes('stock-lock-parent'),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        (new StockStorage($this->secondConnection, $dispatcher))->index([$this->ids->get('stock-lock-second-variant')], $this->context);

        static::assertFalse($this->secondConnection->isTransactionActive());
        static::assertSame(1, (int) $this->secondConnection->fetchOne(
            'SELECT available FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-second-variant')]
        ));
    }

    public function testSiblingVariantsShareInheritedPolicyLocksInOuterTransactions(): void
    {
        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $this->firstConnection->beginTransaction();
        (new StockStorage($this->firstConnection, $dispatcher))->index([$this->ids->get('stock-lock-first-variant')], $this->context);

        $this->secondConnection->beginTransaction();
        (new StockStorage($this->secondConnection, $dispatcher))->index([$this->ids->get('stock-lock-second-variant')], $this->context);

        static::assertTrue($this->firstConnection->isTransactionActive());
        static::assertTrue($this->secondConnection->isTransactionActive());
        static::assertSame(1, (int) $this->secondConnection->fetchOne(
            'SELECT available FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-second-variant')]
        ));
    }

    #[DataProvider('availabilityOperationProvider')]
    public function testAvailabilityUsesParentPolicyCommittedAfterOuterSnapshot(bool $alterStock): void
    {
        $parentUpdated = false;
        $storage = new StockStorage($this->firstConnection, static::createStub(EventDispatcherInterface::class));

        RetryableTransaction::retryable($this->firstConnection, function () use ($storage, $alterStock, &$parentUpdated): void {
            $minimumPurchase = $this->firstConnection->fetchOne(
                'SELECT min_purchase FROM product WHERE id = :id',
                ['id' => $this->ids->getBytes('stock-lock-parent')]
            );

            if (!$parentUpdated) {
                static::assertSame(1, (int) $minimumPurchase);
                $this->secondConnection->update('product', ['min_purchase' => 4], ['id' => $this->ids->getBytes('stock-lock-parent')]);
                $parentUpdated = true;
            }

            if ($alterStock) {
                $storage->alter([
                    new StockAlteration(
                        $this->ids->get('line-item'),
                        $this->ids->get('stock-lock-first-variant'),
                        quantityBefore: 0,
                        newQuantity: 1,
                    ),
                ], $this->context);
            } else {
                $storage->index([$this->ids->get('stock-lock-first-variant')], $this->context);
            }
        });

        static::assertSame($alterStock ? 2 : 3, (int) $this->secondConnection->fetchOne(
            'SELECT stock FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-first-variant')]
        ));
        static::assertSame(0, (int) $this->secondConnection->fetchOne(
            'SELECT available FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-first-variant')]
        ));
    }

    public static function availabilityOperationProvider(): \Generator
    {
        yield 'availability indexing' => [false];
        yield 'stock alteration' => [true];
    }

    public function testAvailabilityUsesUncommittedParentPolicyFromSameTransaction(): void
    {
        $this->firstConnection->beginTransaction();
        $this->firstConnection->update('product', ['min_purchase' => 4], ['id' => $this->ids->getBytes('stock-lock-parent')]);

        (new StockStorage($this->firstConnection, static::createStub(EventDispatcherInterface::class)))
            ->index([$this->ids->get('stock-lock-first-variant')], $this->context);

        static::assertSame(0, (int) $this->firstConnection->fetchOne(
            'SELECT available FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-first-variant')]
        ));
        static::assertSame(1, (int) $this->secondConnection->fetchOne(
            'SELECT min_purchase FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-parent')]
        ));
    }

    private function createConnection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);

        return new Connection(
            array_merge($connection->getParams(), ['dbname' => $connection->getDatabase() ?? '']),
            $connection->getDriver(),
            $connection->getConfiguration(),
        );
    }
}
