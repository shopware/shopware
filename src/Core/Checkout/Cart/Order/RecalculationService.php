<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class RecalculationService
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<OrderAddressCollection> $orderAddressRepository
     * @param EntityRepository<CustomerAddressCollection> $customerAddressRepository
     * @param EntityRepository<OrderLineItemCollection> $orderLineItemRepository
     * @param EntityRepository<OrderDeliveryCollection> $orderDeliveryRepository
     */
    public function __construct(
        protected EntityRepository $orderRepository,
        protected OrderConverter $orderConverter,
        protected CartService $cartService,
        protected EntityRepository $productRepository,
        protected EntityRepository $orderAddressRepository,
        protected EntityRepository $customerAddressRepository,
        protected EntityRepository $orderLineItemRepository,
        protected EntityRepository $orderDeliveryRepository,
        protected Processor $processor,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly PromotionItemBuilder $promotionItemBuilder,
    ) {
    }

    /**
     * @param array<string, array<string, bool>|string> $salesChannelContextOptions
     *
     * @throws CustomerNotLoggedInException
     * @throws CartException
     * @throws OrderException
     * @throws EmptyCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function recalculate(string $orderId, Context $context, array $salesChannelContextOptions = []): ErrorCollection
    {
        $order = $this->fetchOrder($orderId, $context);

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context, $salesChannelContextOptions);
        $cart = $this->orderConverter->convertToCart($order, $context);
        $recalculatedCart = $this->recalculateCart($cart, $salesChannelContext);

        $conversionContext = $this->getOrderConversionContext()->setIncludeDeliveries($cart->getLineItems()->count() > 0);
        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);

        $this->upsertRecalculatedOrder($orderData, $order, $salesChannelContext->getContext(), true);

        return $recalculatedCart->getErrors();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed and is replaced by {@see recalculate}
     *
     * @param array<string, array<string, bool>|string> $salesChannelContextOptions
     *
     * @throws CustomerNotLoggedInException
     * @throws CartException
     * @throws OrderException
     * @throws EmptyCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function recalculateOrder(string $orderId, Context $context, array $salesChannelContextOptions = []): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', self::class . '::recalculate')
        );

        $this->recalculate($orderId, $context, $salesChannelContextOptions);
    }

    /**
     * @throws OrderException
     * @throws InconsistentCriteriaIdsException
     * @throws CartException
     * @throws ProductNotFoundException
     */
    public function addProductToOrder(string $orderId, string $productId, int $quantity, Context $context): void
    {
        $this->validateProduct($productId, $context);
        $lineItem = (new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $quantity))
            ->setRemovable(true)
            ->setStackable(true);

        $order = $this->fetchOrder($orderId, $context);

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);
        $cart->add($lineItem);

        $recalculatedCart = $this->recalculateCart($cart, $salesChannelContext);

        $recalculatedLineItem = $recalculatedCart->get($lineItem->getId());
        if ($recalculatedLineItem?->isShippingCostAware()) {
            $this->addLineItemToDeliveryPosition($recalculatedLineItem, $recalculatedCart);
        }

        $conversionContext = $this->getOrderConversionContext()->setIncludeDeliveries(true);
        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);

        $this->upsertRecalculatedOrder($orderData, $order, $salesChannelContext->getContext());
    }

    /**
     * @throws OrderException
     * @throws InconsistentCriteriaIdsException
     * @throws CartException
     */
    public function addCustomLineItem(string $orderId, LineItem $lineItem, Context $context): void
    {
        $order = $this->fetchOrder($orderId, $context);

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);
        $cart->add($lineItem);

        $recalculatedCart = $this->recalculateCart($cart, $salesChannelContext);

        $recalculatedLineItem = $recalculatedCart->get($lineItem->getId());
        if ($recalculatedLineItem?->isShippingCostAware()) {
            $this->addLineItemToDeliveryPosition($recalculatedLineItem, $recalculatedCart);
        }

        $conversionContext = $this->getOrderConversionContext();
        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);

        $this->upsertRecalculatedOrder($orderData, $order, $salesChannelContext->getContext());
    }

    public function addPromotionLineItem(string $orderId, string $code, Context $context): Cart
    {
        $order = $this->fetchOrder($orderId, $context);

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);

        $promotionLineItem = $this->promotionItemBuilder->buildPlaceholderItem($code);

        $cart->add($promotionLineItem);
        $recalculatedCart = $this->recalculateCart($cart, $salesChannelContext);

        $conversionContext = $this->getOrderConversionContext();
        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);

        $this->upsertRecalculatedOrder($orderData, $order, $salesChannelContext->getContext());

        return $recalculatedCart;
    }

    public function applyAutomaticPromotions(string $orderId, Context $context): ErrorCollection
    {
        $options[SalesChannelContextService::PERMISSIONS] = [
            ...OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS,
            CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS => false,
        ];

        return $this->recalculate($orderId, $context, $options);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use {@see applyAutomaticPromotions} instead.
     */
    public function toggleAutomaticPromotion(string $orderId, Context $context, bool $skipAutomaticPromotions = true): Cart
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', self::class . '::applyAutomaticPromotions')
        );

        $order = $this->fetchOrder($orderId, $context);

        $options[SalesChannelContextService::PERMISSIONS] = [
            ...OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS,
            CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS => false,
            CheckoutPermissions::PIN_MANUAL_PROMOTIONS => false,
            CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS => $skipAutomaticPromotions,
        ];

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext(
            $order,
            $context,
            $options,
        );

        $cart = $this->orderConverter->convertToCart($order, $context);

        $recalculatedCart = $this->recalculateCart($cart, $salesChannelContext);

        $conversionContext = $this->getOrderConversionContext()->setIncludeDeliveries(!$skipAutomaticPromotions);
        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $salesChannelContext, $conversionContext);

        $this->upsertRecalculatedOrder($orderData, $order, $salesChannelContext->getContext(), true);

        return $recalculatedCart;
    }

    /**
     * @throws AddressNotFoundException
     * @throws OrderException
     * @throws InconsistentCriteriaIdsException
     */
    public function replaceOrderAddressWithCustomerAddress(string $orderAddressId, string $customerAddressId, Context $context): void
    {
        $this->validateOrderAddress($orderAddressId, $context);

        $customerAddress = $this->customerAddressRepository->search(new Criteria([$customerAddressId]), $context)->getEntities()->first();
        if (!$customerAddress) {
            throw CartException::addressNotFound($customerAddressId);
        }

        $newOrderAddress = AddressTransformer::transform($customerAddress);
        $newOrderAddress['id'] = $orderAddressId;
        $this->orderAddressRepository->upsert([$newOrderAddress], $context);
    }

    /**
     * @param array<string, mixed> $orderData
     */
    private function upsertRecalculatedOrder(
        array $orderData,
        OrderEntity $order,
        Context $context,
        bool $allowLineItemsDeletion = false,
    ): void {
        $orderData['id'] = $order->getId();
        $orderData['stateId'] = $order->getStateId();

        if ($order->getPrimaryOrderDelivery()?->getStateId() && isset($orderData['deliveries'][0])) {
            $orderData['deliveries'][0]['stateId'] = $order->getPrimaryOrderDelivery()->getStateId();
        }

        if (!Feature::isActive('v6.8.0.0')) {
            if ($order->getDeliveries()?->first()?->getStateId() && isset($orderData['deliveries'][0])) {
                $orderData['deliveries'][0]['stateId'] = $order->getDeliveries()->first()->getStateId();
            }
        }

        if ($allowLineItemsDeletion) {
            $this->deleteOldLineItems($orderData, $order, $context);
        }

        $this->deleteOldDiscountDeliveries($orderData, $order, $context);

        // change scope to be able to write protected state fields of transactions and deliveries
        $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->orderRepository->upsert([$orderData], $context));
    }

    /**
     * @param array<string, mixed> $orderData
     */
    private function deleteOldLineItems(array $orderData, OrderEntity $order, Context $context): void
    {
        $newIds = \array_column($orderData['lineItems'], 'id');
        $originalIds = $order->getLineItems()?->getKeys() ?? [];
        $toDeleteIds = \array_values(\array_diff($originalIds, $newIds));

        if (\count($toDeleteIds) > 0) {
            $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->orderLineItemRepository->delete(
                \array_map(static fn (string $id) => ['id' => $id], $toDeleteIds),
                $context
            ));
        }
    }

    /**
     * Any recalculation to delivery discounts will create new deliveries ({@see PromotionDeliveryCalculator}).
     * Therefore, all "ghost" deliveries have to be deleted.
     *
     * @param array<string, mixed> $orderData
     */
    private function deleteOldDiscountDeliveries(array $orderData, OrderEntity $order, Context $context): void
    {
        /** @var array<array{shippingCosts: CalculatedPrice}>|null $deliveries */
        $deliveries = $orderData['deliveries'] ?? null;
        // There always has to be the primary delivery if deliveries where transformed.
        // If no deliveries are present, we should skip to avoid deleting deliveries unwillingly.
        if (!$deliveries) {
            return;
        }

        $newIds = \array_column(
            \array_filter($deliveries, static fn (array $delivery) => $delivery['shippingCosts']->getTotalPrice() < 0),
            'id',
        );
        $originalIds = $order->getDeliveries()?->filter(
            static fn (OrderDeliveryEntity $delivery) => $delivery->getShippingCosts()->getTotalPrice() < 0,
        )->getKeys() ?? [];
        $toDeleteIds = \array_values(\array_diff($originalIds, $newIds));

        if (\count($toDeleteIds) > 0) {
            $context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->orderDeliveryRepository->delete(
                \array_map(static fn (string $id) => ['id' => $id], $toDeleteIds),
                $context
            ));
        }
    }

    private function addLineItemToDeliveryPosition(LineItem $item, Cart $cart): void
    {
        $delivery = $cart->getDeliveries()->getPrimaryDelivery(
            $cart->getExtensionOfType(OrderConverter::ORIGINAL_PRIMARY_ORDER_DELIVERY, IdStruct::class)?->getId()
        );

        if (!Feature::isActive('v6.8.0.0')) {
            $delivery = $cart->getDeliveries()->first();
        }

        if (!$delivery) {
            return;
        }

        $calculatedPrice = $item->getPrice();
        \assert($calculatedPrice !== null);

        $position = new DeliveryPosition($item->getId(), clone $item, $item->getQuantity(), $calculatedPrice, $delivery->getDeliveryDate());

        $delivery->getPositions()->add($position);
    }

    private function fetchOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociations([
                'primaryOrderDelivery',
                'lineItems.downloads',
                'transactions.stateMachineState',
                'deliveries.shippingMethod.tax',
                'deliveries.shippingMethod.deliveryTime',
                'deliveries.positions.orderLineItem',
                'deliveries.shippingOrderAddress.country',
                'deliveries.shippingOrderAddress.countryState',
            ]);

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        $this->validateOrder($order, $orderId);

        return $order;
    }

    /**
     * @throws OrderException
     *
     * @phpstan-assert OrderEntity $order
     */
    private function validateOrder(?OrderEntity $order, string $orderId): void
    {
        if (!$order) {
            throw CartException::orderNotFound($orderId);
        }

        $this->checkVersion($order);
    }

    /**
     * @throws ProductNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateProduct(string $productId, Context $context): void
    {
        $total = $this->productRepository->searchIds(new Criteria([$productId]), $context)->getTotal();
        if ($total === 0) {
            throw CartException::productNotFound($productId);
        }
    }

    private function checkVersion(Entity $entity): void
    {
        if ($entity->getVersionId() === Defaults::LIVE_VERSION) {
            throw OrderException::canNotRecalculateLiveVersion($entity->getUniqueIdentifier());
        }
    }

    /**
     * @throws AddressNotFoundException
     * @throws OrderException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateOrderAddress(string $orderAddressId, Context $context): void
    {
        $address = $this->orderAddressRepository->search(new Criteria([$orderAddressId]), $context)->getEntities()->first();
        if (!$address) {
            throw CartException::addressNotFound($orderAddressId);
        }

        $this->checkVersion($address);
    }

    private function recalculateCart(Cart $cart, SalesChannelContext $context): Cart
    {
        // we switch to the live version that we don't have to consider live version fallbacks inside the calculation
        return $context->live(function ($live) use ($cart): Cart {
            /** @deprecated tag:v6.8.0 - `$isRecalculation` will be removed */
            $behavior = new CartBehavior($live->getPermissions(), true, isRecalculation: !Feature::isActive('v6.8.0.0'));

            // all prices are now prepared for calculation - starts the cart calculation
            $cart = $this->processor->process($cart, $live, $behavior);

            // validate cart against the context rules
            $validatedCart = $this->cartRuleLoader->loadByCart($live, $cart, $behavior)->getCart();
            $validatedCart->addErrors(...$cart->getErrors()->filter(fn (Error $error) => !$error->isPersistent()));

            return $validatedCart;
        });
    }

    private function getOrderConversionContext(): OrderConversionContext
    {
        return (new OrderConversionContext())
            ->setIncludeCustomer(false)
            ->setIncludeBillingAddress(false)
            ->setIncludeTransactions(false)
            ->setIncludePersistentData(false);
    }
}
