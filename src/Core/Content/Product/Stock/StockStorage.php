<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class StockStorage extends AbstractStockStorage
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher
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

        $this->updateAvailableFlag(array_column($changes, 'productId'), $context);

        $this->dispatcher->dispatch(new ProductStockAlteredEvent(array_column($changes, 'productId'), $context));
    }

    /**
     * @param list<string> $productIds
     */
    public function index(array $productIds, Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $this->updateAvailableFlag($productIds, $context);
    }

    /**
     * @param list<string> $ids
     */
    private function updateAvailableFlag(array $ids, Context $context): void
    {
        $ids = array_values(array_filter(array_unique($ids)));

        if ($ids === []) {
            return;
        }

        $bytes = Uuid::fromHexToBytesList($ids);
        sort($bytes);

        $version = Uuid::fromHexToBytes($context->getVersionId());

        [$before, $after] = RetryableTransaction::retryable($this->connection, function () use ($bytes, $version): array {
            $params = ['ids' => $bytes, 'version' => $version];

            // Lock only the products whose availability is recalculated. Reading the inherited values in a
            // separate, non-locking SELECT prevents variants from contending through their shared parent.
            $this->connection->executeStatement(
                'SELECT id FROM product WHERE id IN (:ids) AND version_id = :version FOR UPDATE',
                $params,
                ['ids' => ArrayParameterType::BINARY]
            );

            /**
             * @var array<string, array{current_available: mixed, calculated_available: mixed}> $availability
             */
            $availability = $this->connection->fetchAllAssociativeIndexed('
            SELECT LOWER(HEX(product.id)),
                product.available AS current_available,
                IFNULL((
                    COALESCE(product.is_closeout, parent.is_closeout, 0) * product.stock
                    >=
                    COALESCE(product.is_closeout, parent.is_closeout, 0) * IFNULL(product.min_purchase, parent.min_purchase)
                ), 0) AS calculated_available
            FROM product
            LEFT JOIN product parent
                ON parent.id = product.parent_id
                AND parent.version_id = product.version_id
            WHERE product.id IN (:ids)
            AND product.version_id = :version
        ', $params, ['ids' => ArrayParameterType::BINARY]);

            $before = [];
            $cases = [];
            foreach ($availability as $id => $values) {
                $index = \count($cases);
                $before[$id] = $values['current_available'];
                $cases[] = \sprintf('WHEN :id%d THEN :available%d', $index, $index);
                $params['id' . $index] = Uuid::fromHexToBytes($id);
                $params['available' . $index] = (int) $values['calculated_available'];
            }

            if ($cases !== []) {
                $this->connection->executeStatement(
                    \sprintf(
                        'UPDATE product SET available = CASE id %s ELSE available END, updated_at = NOW() WHERE id IN (:ids) AND version_id = :version',
                        \implode(' ', $cases)
                    ),
                    $params,
                    ['ids' => ArrayParameterType::BINARY]
                );
            }

            $after = $this->connection->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), available FROM product WHERE id IN (:ids) AND product.version_id = :version',
                ['ids' => $bytes, 'version' => $version],
                ['ids' => ArrayParameterType::BINARY]
            );

            return [$before, $after];
        });

        $updated = [];
        foreach ($before as $id => $available) {
            if ($available !== $after[$id]) {
                $updated[] = $id;
            }
        }

        if ($updated !== []) {
            $this->dispatcher->dispatch(new ProductNoLongerAvailableEvent($updated, $context));
        }
    }
}
