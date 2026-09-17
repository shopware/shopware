<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
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

    private StockStorageConcurrencyConnection $firstConnection;

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

    #[DataProvider('outerTransactionProvider')]
    public function testSiblingVariantsShareInheritedPolicyLocks(bool $hasOuterTransaction): void
    {
        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $this->firstConnection->beginTransaction();
        (new StockStorage($this->firstConnection, $dispatcher))->index([$this->ids->get('stock-lock-first-variant')], $this->context);

        if ($hasOuterTransaction) {
            $this->secondConnection->beginTransaction();
        }

        (new StockStorage($this->secondConnection, $dispatcher))->index([$this->ids->get('stock-lock-second-variant')], $this->context);

        static::assertTrue($this->firstConnection->isTransactionActive());
        static::assertSame($hasOuterTransaction, $this->secondConnection->isTransactionActive());
        static::assertSame(1, (int) $this->secondConnection->fetchOne(
            'SELECT available FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-second-variant')]
        ));
    }

    #[DataProvider('outerTransactionProvider')]
    public function testParentPolicyCannotChangeBetweenAvailabilityReadAndWrite(bool $hasOuterTransaction): void
    {
        if ($hasOuterTransaction) {
            $this->firstConnection->beginTransaction();
        }

        $parentWriteBlocked = false;
        $this->firstConnection->afterAvailabilityRead = function () use (&$parentWriteBlocked): void {
            // Interleave a real parent write after the inherited policy was read, but before availability is written.
            try {
                $this->secondConnection->update('product', ['min_purchase' => 4], ['id' => $this->ids->getBytes('stock-lock-parent')]);
            } catch (LockWaitTimeoutException) {
                $parentWriteBlocked = true;
            }
        };

        $storage = new StockStorage($this->firstConnection, static::createStub(EventDispatcherInterface::class));
        $storage->index([$this->ids->get('stock-lock-first-variant')], $this->context);

        static::assertTrue($parentWriteBlocked, 'The inherited policy must remain unchanged until availability is written.');
        static::assertSame(1, (int) $this->secondConnection->fetchOne(
            'SELECT min_purchase FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-parent')]
        ));
        static::assertSame(1, (int) $this->firstConnection->fetchOne(
            'SELECT available FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-first-variant')]
        ));

        if ($hasOuterTransaction) {
            $this->firstConnection->commit();
        }

        // Once indexing commits, the parent can change and a new calculation uses the new policy.
        $this->secondConnection->update('product', ['min_purchase' => 4], ['id' => $this->ids->getBytes('stock-lock-parent')]);
        $storage->index([$this->ids->get('stock-lock-first-variant')], $this->context);

        static::assertSame(0, (int) $this->secondConnection->fetchOne(
            'SELECT available FROM product WHERE id = :id',
            ['id' => $this->ids->getBytes('stock-lock-first-variant')]
        ));
    }

    public static function outerTransactionProvider(): \Generator
    {
        yield 'standalone availability update' => [false];
        yield 'inside an outer transaction' => [true];
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

    private function createConnection(): StockStorageConcurrencyConnection
    {
        $connection = static::getContainer()->get(Connection::class);

        return new StockStorageConcurrencyConnection(
            array_merge($connection->getParams(), ['dbname' => $connection->getDatabase() ?? '']),
            $connection->getDriver(),
            $connection->getConfiguration(),
        );
    }
}

/**
 * @internal
 */
#[Package('inventory')]
class StockStorageConcurrencyConnection extends Connection
{
    public ?\Closure $afterAvailabilityRead = null;

    public function fetchAllAssociativeIndexed(string $query, array $params = [], array $types = []): array
    {
        $result = parent::fetchAllAssociativeIndexed($query, $params, $types);
        $callback = $this->afterAvailabilityRead;
        $this->afterAvailabilityRead = null;
        $callback?->__invoke();

        return $result;
    }
}
