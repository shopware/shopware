<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

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
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
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
        private readonly Connection $connection,
        private readonly AbstractStockStorage $stockStorage,
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

        // Stock is locked and checked in the transaction that writes the order and decrements the stock,
        // so concurrent checkouts cannot take the same last units.
        RetryableWriteTransaction::retryable($this->connection, function () use ($order, $cart, $context): void {
            $this->assertStock($cart, $context->getContext());

            $context->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($order): void {
                $this->orderRepository->create([$order], $context);
            });
        });

        return $order['id'];
    }

    private function assertStock(Cart $cart, Context $context): void
    {
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

        $errors = new ErrorCollection();
        foreach ($this->stockStorage->lockForOrder($quantities, $context) as $productId) {
            $errors->add(new ProductOutOfStockError($productId, $labels[$productId] ?? $productId));
        }

        if ($errors->count() > 0) {
            throw CartException::invalidCart($errors);
        }
    }
}
