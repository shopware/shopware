<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MissingOrderRelationException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\CustomerTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\DeliveryTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\LineItemTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\TransactionTransformer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderConverter
{
    public const CART_CONVERTED_TO_ORDER_EVENT = 'cart.convertedToOrder.event';

    public const CART_TYPE = 'recalculation';

    public const ORIGINAL_ID = 'originalId';

    public const ORIGINAL_ORDER_NUMBER = 'originalOrderNumber';

    private const ADMIN_EDIT_ORDER_PERMISSIONS = [
        ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
        ProductCartProcessor::SKIP_PRODUCT_RECALCULATION => true,
        DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true,
        DeliveryProcessor::SKIP_DELIVERY_TAX_RECALCULATION => true,
        PromotionCollector::SKIP_PROMOTION => true,
    ];

    /**
     * @var EntityRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var SalesChannelContextFactory
     */
    protected $salesChannelContextFactory;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    /**
     * @var OrderDefinition
     */
    private $orderDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderAddressRepository;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        SalesChannelContextFactory $salesChannelContextFactory,
        StateMachineRegistry $stateMachineRegistry,
        EventDispatcherInterface $eventDispatcher,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        OrderDefinition $orderDefinition,
        EntityRepositoryInterface $orderAddressRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->orderDefinition = $orderDefinition;
        $this->orderAddressRepository = $orderAddressRepository;
    }

    /**
     * @throws DeliveryWithoutAddressException
     */
    public function convertToOrder(Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext): array
    {
        foreach ($cart->getDeliveries() as $delivery) {
            if ($delivery->getLocation()->getAddress() !== null || $delivery->hasExtensionOfType(self::ORIGINAL_ID, IdStruct::class)) {
                continue;
            }

            throw new DeliveryWithoutAddressException();
        }
        $data = CartTransformer::transform(
            $cart,
            $context,
            $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context->getContext())->getId()
        );

        if ($conversionContext->shouldIncludeCustomer()) {
            $data['orderCustomer'] = CustomerTransformer::transform($context->getCustomer());
        }

        $data['languageId'] = $context->getSalesChannel()->getLanguageId();

        $convertedLineItems = LineItemTransformer::transformCollection($cart->getLineItems());
        $shippingAddresses = [];

        if ($conversionContext->shouldIncludeDeliveries()) {
            $shippingAddresses = AddressTransformer::transformCollection($cart->getDeliveries()->getAddresses(), true);
            $data['deliveries'] = DeliveryTransformer::transformCollection(
                $cart->getDeliveries(),
                $convertedLineItems,
                $this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context->getContext())->getId(),
                $context->getContext(),
                $shippingAddresses
            );
        }

        if ($conversionContext->shouldIncludeBillingAddress()) {
            $customerAddressId = $context->getCustomer()->getActiveBillingAddress()->getId();

            if (array_key_exists($customerAddressId, $shippingAddresses)) {
                $billingAddressId = $shippingAddresses[$customerAddressId]['id'];
            } else {
                $billingAddress = AddressTransformer::transform($context->getCustomer()->getActiveBillingAddress());
                $data['addresses'] = [$billingAddress];
                $billingAddressId = $billingAddress['id'];
            }
            $data['billingAddressId'] = $billingAddressId;
        }

        if ($conversionContext->shouldIncludeTransactions()) {
            $data['transactions'] = TransactionTransformer::transformCollection(
                $cart->getTransactions(),
                $this->stateMachineRegistry->getInitialState(OrderTransactionStates::STATE_MACHINE, $context->getContext())->getId(),
                $context->getContext()
            );
        }

        $data['lineItems'] = array_values($convertedLineItems);

        /** @var IdStruct|null $idStruct */
        $idStruct = $cart->getExtensionOfType(self::ORIGINAL_ID, IdStruct::class);
        $data['id'] = $idStruct ? $idStruct->getId() : Uuid::randomHex();

        /** @var IdStruct|null $orderNumberStruct */
        $orderNumberStruct = $cart->getExtensionOfType(self::ORIGINAL_ORDER_NUMBER, IdStruct::class);
        if ($orderNumberStruct !== null) {
            $data['orderNumber'] = $orderNumberStruct->getId();
        } else {
            $data['orderNumber'] = $this->numberRangeValueGenerator->getValue(
                $this->orderDefinition->getEntityName(),
                $context->getContext(),
                $context->getSalesChannel()->getId()
            );
        }

        $event = new CartConvertedEvent($cart, $data, $context, $conversionContext);
        $this->eventDispatcher->dispatch($event);

        return $event->getConvertedCart();
    }

    /**
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws MissingOrderRelationException
     */
    public function convertToCart(OrderEntity $order, Context $context): Cart
    {
        if ($order->getLineItems() === null) {
            throw new MissingOrderRelationException('lineItems');
        }

        if ($order->getDeliveries() === null) {
            throw new MissingOrderRelationException('deliveries');
        }

        $cart = new Cart(self::CART_TYPE, Uuid::randomHex());
        $cart->setPrice($order->getPrice());
        $cart->addExtension(self::ORIGINAL_ID, new IdStruct($order->getId()));
        $cart->addExtension(self::ORIGINAL_ORDER_NUMBER, new IdStruct($order->getOrderNumber()));
        /* NEXT-708 support:
            - transactions
        */

        $lineItems = LineItemTransformer::transformFlatToNested($order->getLineItems());

        $cart->addLineItems($lineItems);
        $cart->setDeliveries(
            $this->convertDeliveries($order->getDeliveries(), $lineItems)
        );

        $event = new OrderConvertedEvent($order, $cart, $context);
        $this->eventDispatcher->dispatch($event);

        return $event->getConvertedCart();
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function assembleSalesChannelContext(OrderEntity $order, Context $context): SalesChannelContext
    {
        $customerId = $order->getOrderCustomer()->getCustomerId();
        $customerGroupId = null;

        if ($customerId) {
            /** @var CustomerEntity|null $customer */
            $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->get($customerId);
            $customerGroupId = $customer->getGroupId() ?? null;
        }

        /** @var OrderAddressEntity|null $billingAddress */
        $billingAddress = $this->orderAddressRepository->search(new Criteria([$order->getBillingAddressId()]), $context)->get($order->getBillingAddressId());

        $options = [
            SalesChannelContextService::CURRENCY_ID => $order->getCurrencyId(),
            SalesChannelContextService::LANGUAGE_ID => $order->getLanguageId(),
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::COUNTRY_STATE_ID => $billingAddress->getCountryStateId(),
            SalesChannelContextService::CUSTOMER_GROUP_ID => $customerGroupId,
            SalesChannelContextService::PERMISSIONS => self::ADMIN_EDIT_ORDER_PERMISSIONS,
            SalesChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        //get the first not paid transaction or, if all paid, the last transaction
        if ($order->getTransactions()) {
            foreach ($order->getTransactions() as $transaction) {
                $options[SalesChannelContextService::PAYMENT_METHOD_ID] = $transaction->getPaymentMethodId();
                if (
                    $transaction->getStateMachineState() !== null
                    && $transaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_PAID
                ) {
                    break;
                }
            }
        }

        return $this->salesChannelContextFactory->create(Uuid::randomHex(), $order->getSalesChannelId(), $options);
    }

    private function convertDeliveries(OrderDeliveryCollection $orderDeliveries, LineItemCollection $lineItems): DeliveryCollection
    {
        $cartDeliveries = new DeliveryCollection();

        foreach ($orderDeliveries as $orderDelivery) {
            $deliveryDate = new DeliveryDate(
                $orderDelivery->getShippingDateEarliest(),
                $orderDelivery->getShippingDateLatest()
            );

            $deliveryPositions = new DeliveryPositionCollection();

            foreach ($orderDelivery->getPositions() as $position) {
                $identifier = $position->getOrderLineItem()->getIdentifier();

                // line item has been removed and will not be added to delivery
                if ($lineItems->get($identifier) === null) {
                    continue;
                }

                $deliveryPosition = new DeliveryPosition(
                    $identifier,
                    $lineItems->get($identifier),
                    $position->getPrice()->getQuantity(),
                    $position->getPrice(),
                    $deliveryDate
                );
                $deliveryPosition->addExtension(self::ORIGINAL_ID, new IdStruct($position->getId()));

                $deliveryPositions->add($deliveryPosition);
            }

            $cartDelivery = new Delivery(
                $deliveryPositions,
                $deliveryDate,
                $orderDelivery->getShippingMethod(),
                new ShippingLocation(
                    $orderDelivery->getShippingOrderAddress()->getCountry(),
                    $orderDelivery->getShippingOrderAddress()->getCountryState(),
                    null
                ),
                $orderDelivery->getShippingCosts()
            );
            $cartDelivery->addExtension(self::ORIGINAL_ID, new IdStruct($orderDelivery->getId()));

            $cartDeliveries->add($cartDelivery);
        }

        return $cartDeliveries;
    }
}
