<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class OrderPersister implements OrderPersisterInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderConverter $converter,
        private readonly CartSerializationCleaner $cartSerializationCleaner,
        private readonly ?Connection $connection = null,
    ) {
    }

    /**
     * @throws CartException
     * @throws OrderException
     * @throws InvalidCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function persist(Cart $cart, SalesChannelContext $context): string
    {
        if ($cart->getErrors()->blockOrder()) {
            throw CartException::invalidCart($cart->getErrors());
        }

        if (!$context->getCustomer()) {
            throw CartException::customerNotLoggedIn();
        }

        if ($cart->getLineItems()->count() <= 0) {
            throw CartException::cartEmpty();
        }

        // cleanup cart before converting it to an order
        $this->cartSerializationCleaner->cleanupCart($cart);

        $order = $this->converter->convertToOrder($cart, $context, new OrderConversionContext());

        $create = function () use ($order, $cart, $context): void {
            $this->assertCloseoutStock($cart);

            $context->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($order): void {
                $this->orderRepository->create([$order], $context);
            });
        };

        if ($this->connection === null || $context->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            $create();
        } else {
            // Closeout stock is locked and re-checked in the same transaction that writes the order and, through
            // OrderStockSubscriber, decrements the stock. Concurrent checkouts can therefore not both take the
            // last units of a closeout product.
            $connection = $this->connection;
            RetryableTransaction::retryable($connection, static fn () => $connection->transactional($create));
        }

        return $order['id'];
    }

    private function assertCloseoutStock(Cart $cart): void
    {
        if ($this->connection === null) {
            return;
        }

        $quantities = [];
        $labels = [];
        foreach ($cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if ($productId === null || !Uuid::isValid($productId)) {
                continue;
            }
            $productId = strtolower($productId);
            $quantities[$productId] = ($quantities[$productId] ?? 0) + $lineItem->getQuantity();
            $labels[$productId] = (string) $lineItem->getLabel();
        }

        if ($quantities === []) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(product.id)) AS id, product.stock
                FROM product
                LEFT JOIN product parent
                    ON parent.id = product.parent_id
                    AND parent.version_id = product.version_id
                WHERE product.id IN (:ids)
                    AND product.version_id = :version
                    AND COALESCE(product.is_closeout, parent.is_closeout, 0) = 1
                FOR UPDATE
            SQL,
            [
                'ids' => Uuid::fromHexToBytesList(array_keys($quantities)),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        $errors = new ErrorCollection();
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            if ((int) $row['stock'] < $quantities[$id]) {
                $errors->add(new ProductOutOfStockError($id, $labels[$id]));
            }
        }

        if ($errors->count() > 0) {
            throw CartException::invalidCart($errors);
        }
    }
}
