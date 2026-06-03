<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductBackInStockEvent;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\Events\ProductOutOfStockEvent;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Content\Product\Events\ProductStockChangedEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class StockStorage extends AbstractStockStorage
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     *
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EntityRepository $productRepository
    ) {
    }

    public function getDecorated(): AbstractStockStorage
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection
    {
        return new StockDataCollection([]);
    }

    /**
     * @param list<StockAlteration> $changes
     */
    public function alter(array $changes, Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($changes === []) {
            return;
        }

        $sql = <<<'SQL'
            UPDATE product
            SET stock = stock + :quantity, sales = sales - :quantity, available_stock = stock, updated_at = NOW()
            WHERE id = :id AND version_id = :version
        SQL;

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare($sql)
        );

        foreach ($changes as $alteration) {
            $query->execute([
                'quantity' => $alteration->quantityDelta(),
                'id' => Uuid::fromHexToBytes($alteration->productId),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
        }

        $transitions = $this->updateAvailableFlag(array_column($changes, 'productId'), $context);

        $this->dispatcher->dispatch(new ProductStockAlteredEvent(array_column($changes, 'productId'), $context));

        // directional availability events fire only here, on a genuine order-driven stock
        // movement — never from the index() recompute, so product creation cannot emit
        // a spurious back_in_stock (the transitions only contain rows that actually flipped)
        foreach ($transitions['outOfStock'] as $id) {
            $this->dispatcher->dispatch(new ProductOutOfStockEvent($context, $id, $this->createProductLoader($id, $context)));
        }

        foreach ($transitions['backInStock'] as $id) {
            $this->dispatcher->dispatch(new ProductBackInStockEvent($context, $id, $this->createProductLoader($id, $context)));
        }

        $existingProductIds = $this->fetchExistingProductIds(array_column($changes, 'productId'), $context);

        foreach ($changes as $alteration) {
            // a line-item write that does not move quantity (e.g. only referencedId
            // changed) is not a stock change
            if ($alteration->quantityDelta() === 0) {
                continue;
            }

            // the product row may be gone — cancelling an order whose product was
            // hard-deleted still alters stock by referenced_id (a plain column, not a
            // cascading FK), but a missing product is not a stock-change moment and the
            // event's lazy loader would throw productNotFound when a webhook encodes it
            if (!isset($existingProductIds[$alteration->productId])) {
                continue;
            }

            $this->dispatcher->dispatch(new ProductStockChangedEvent(
                $context,
                $alteration->productId,
                $this->createProductLoader($alteration->productId, $context),
                null,
                $alteration->quantityDelta()
            ));
        }
    }

    /**
     * @param list<string> $productIds
     */
    public function index(array $productIds, Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        // index() recomputes the available flag (and the legacy, non-flow
        // ProductNoLongerAvailableEvent) for creation, direct writes and variant
        // recompute alike. It deliberately does NOT emit the directional
        // product.out_of_stock / product.back_in_stock business events: on insert the
        // flag transitions from the DB default 0 to its computed value, which is not a
        // "back in stock" moment. Those directional events belong to the stock-movement
        // domain action (alter); direct/admin/import stock writes are observable through
        // product.stock.changed instead.
        $this->updateAvailableFlag($productIds, $context);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, true>
     */
    private function fetchExistingProductIds(array $ids, Context $context): array
    {
        $ids = array_values(array_filter(array_unique($ids)));
        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM product WHERE id IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => Uuid::fromHexToBytes($context->getVersionId())],
            ['ids' => ArrayParameterType::BINARY]
        );

        return array_fill_keys($rows, true);
    }

    /**
     * @param list<string> $ids
     *
     * @return array{outOfStock: list<string>, backInStock: list<string>}
     */
    private function updateAvailableFlag(array $ids, Context $context): array
    {
        $ids = array_filter(array_unique($ids));

        if ($ids === []) {
            return ['outOfStock' => [], 'backInStock' => []];
        }

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = '
            UPDATE product
            LEFT JOIN product parent
                ON parent.id = product.parent_id
                AND parent.version_id = product.version_id

            SET product.available = IFNULL((
                COALESCE(product.is_closeout, parent.is_closeout, 0) * product.stock
                >=
                COALESCE(product.is_closeout, parent.is_closeout, 0) * IFNULL(product.min_purchase, parent.min_purchase)
            ), 0),
                product.updated_at = NOW()
            WHERE product.id IN (:ids)
            AND product.version_id = :version
        ';

        $before = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), available FROM product WHERE id IN (:ids) AND product.version_id = :version',
            ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
            ['ids' => ArrayParameterType::BINARY]
        );

        RetryableQuery::retryable($this->connection, function () use ($sql, $context, $bytes): void {
            $this->connection->executeStatement(
                $sql,
                ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        $after = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), available FROM product WHERE id IN (:ids) AND product.version_id = :version',
            ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
            ['ids' => ArrayParameterType::BINARY]
        );

        $updated = [];
        $outOfStock = [];
        $backInStock = [];
        foreach ($before as $id => $available) {
            if ($available !== $after[$id]) {
                $isAvailable = (bool) $after[$id];
                $id = (string) $id;
                $updated[] = $id;

                if ($isAvailable) {
                    $backInStock[] = $id;
                } else {
                    $outOfStock[] = $id;
                }
            }
        }

        if ($updated !== []) {
            $this->dispatcher->dispatch(new ProductNoLongerAvailableEvent($updated, $context));
        }

        return ['outOfStock' => $outOfStock, 'backInStock' => $backInStock];
    }

    /**
     * @return \Closure(): ProductEntity
     */
    private function createProductLoader(string $productId, Context $context): \Closure
    {
        return function () use ($productId, $context): ProductEntity {
            $product = $this->productRepository->search(new Criteria([$productId]), $context)->getEntities()->get($productId);
            if (!$product instanceof ProductEntity) {
                throw ProductException::productNotFound($productId);
            }

            return $product;
        };
    }
}
