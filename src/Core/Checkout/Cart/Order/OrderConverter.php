<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
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
use Shopware\Core\Checkout\Cart\Exception\OrderInconsistentException;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\CustomerTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\DeliveryTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\LineItemTransformer;
use Shopware\Core\Checkout\Cart\Order\Transformer\TransactionTransformer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @package checkout
 */
class OrderConverter
{
    public const CART_CONVERTED_TO_ORDER_EVENT = 'cart.convertedToOrder.event';

    public const CART_TYPE = 'recalculation';

    public const ORIGINAL_ID = 'originalId';

    public const ORIGINAL_ORDER_NUMBER = 'originalOrderNumber';

    public const ADMIN_EDIT_ORDER_PERMISSIONS = [
        ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
        ProductCartProcessor::SKIP_PRODUCT_RECALCULATION => true,
        DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true,
        DeliveryProcessor::SKIP_DELIVERY_TAX_RECALCULATION => true,
        PromotionCollector::SKIP_PROMOTION => true,
        ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => true,
        ProductCartProcessor::KEEP_INACTIVE_PRODUCT => true,
    ];

    protected EntityRepository $customerRepository;

    protected AbstractSalesChannelContextFactory $salesChannelContextFactory;

    protected EventDispatcherInterface $eventDispatcher;

    private NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    private OrderDefinition $orderDefinition;

    private EntityRepository $orderAddressRepository;

    private InitialStateIdLoader $initialStateIdLoader;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $customerRepository,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        EventDispatcherInterface $eventDispatcher,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        OrderDefinition $orderDefinition,
        EntityRepository $orderAddressRepository,
        InitialStateIdLoader $initialStateIdLoader
    ) {
        $this->customerRepository = $customerRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
        $this->orderDefinition = $orderDefinition;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->initialStateIdLoader = $initialStateIdLoader;
    }

    /**
     * @throws DeliveryWithoutAddressException
     *
     * @return array<string, mixed|float|string|array<int, array<string, string|int|bool|mixed>>|null>
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
            $this->initialStateIdLoader->get(OrderStates::STATE_MACHINE),
            $conversionContext->shouldIncludeOrderDate()
        );

        if ($conversionContext->shouldIncludeCustomer()) {
            $customer = $context->getCustomer();
            if ($customer === null) {
                throw CartException::customerNotLoggedIn();
            }

            $data['orderCustomer'] = CustomerTransformer::transform($customer);
        }

        $data['languageId'] = $context->getLanguageId();

        $convertedLineItems = LineItemTransformer::transformCollection($cart->getLineItems());
        $shippingAddresses = [];

        if ($conversionContext->shouldIncludeDeliveries()) {
            $shippingAddresses = AddressTransformer::transformCollection($cart->getDeliveries()->getAddresses(), true);
            $data['deliveries'] = DeliveryTransformer::transformCollection(
                $cart->getDeliveries(),
                $convertedLineItems,
                $this->initialStateIdLoader->get(OrderDeliveryStates::STATE_MACHINE),
                $context->getContext(),
                $shippingAddresses
            );
        }

        if ($conversionContext->shouldIncludeBillingAddress()) {
            $customer = $context->getCustomer();
            if ($customer === null) {
                throw CartException::customerNotLoggedIn();
            }

            $activeBillingAddress = $customer->getActiveBillingAddress();
            if ($activeBillingAddress === null) {
                throw new AddressNotFoundException('');
            }
            $customerAddressId = $activeBillingAddress->getId();

            if (\array_key_exists($customerAddressId, $shippingAddresses)) {
                $billingAddressId = $shippingAddresses[$customerAddressId]['id'];
            } else {
                $billingAddress = AddressTransformer::transform($activeBillingAddress);
                $data['addresses'] = [$billingAddress];
                $billingAddressId = $billingAddress['id'];
            }
            $data['billingAddressId'] = $billingAddressId;
        }

        if ($conversionContext->shouldIncludeTransactions()) {
            $data['transactions'] = TransactionTransformer::transformCollection(
                $cart->getTransactions(),
                $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE),
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

        $data['ruleIds'] = $context->getRuleIds();

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
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::missingAssociation('lineItems');
            }

            throw new MissingOrderRelationException('lineItems');
        }

        if ($order->getDeliveries() === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::missingAssociation('deliveries');
            }

            throw new MissingOrderRelationException('deliveries');
        }

        $cart = new Cart(self::CART_TYPE, Uuid::randomHex());
        $cart->setPrice($order->getPrice());
        $cart->setCustomerComment($order->getCustomerComment());
        $cart->setAffiliateCode($order->getAffiliateCode());
        $cart->setCampaignCode($order->getCampaignCode());
        $cart->addExtension(self::ORIGINAL_ID, new IdStruct($order->getId()));
        $orderNumber = $order->getOrderNumber();
        if ($orderNumber === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::missingOrderNumber($order->getId());
            }

            throw new OrderInconsistentException($order->getId(), 'orderNumber is required');
        }

        $cart->addExtension(self::ORIGINAL_ORDER_NUMBER, new IdStruct($orderNumber));
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
     * @param array<string, array<string, bool>|string> $overrideOptions
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function assembleSalesChannelContext(OrderEntity $order, Context $context, array $overrideOptions = []): SalesChannelContext
    {
        if ($order->getTransactions() === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::missingAssociation('transactions');
            }

            throw new MissingOrderRelationException('transactions');
        }
        if ($order->getOrderCustomer() === null) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::missingAssociation('orderCustomer');
            }

            throw new MissingOrderRelationException('orderCustomer');
        }

        $customerId = $order->getOrderCustomer()->getCustomerId();
        $customerGroupId = null;

        if ($customerId) {
            /** @var CustomerEntity|null $customer */
            $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->get($customerId);
            if ($customer !== null) {
                $customerGroupId = $customer->getGroupId();
            }
        }

        $billingAddressId = $order->getBillingAddressId();
        /** @var OrderAddressEntity|null $billingAddress */
        $billingAddress = $this->orderAddressRepository->search(new Criteria([$billingAddressId]), $context)->get($billingAddressId);
        if ($billingAddress === null) {
            throw new AddressNotFoundException($billingAddressId);
        }

        $options = [
            SalesChannelContextService::CURRENCY_ID => $order->getCurrencyId(),
            SalesChannelContextService::LANGUAGE_ID => $order->getLanguageId(),
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::COUNTRY_STATE_ID => $billingAddress->getCountryStateId(),
            SalesChannelContextService::CUSTOMER_GROUP_ID => $customerGroupId,
            SalesChannelContextService::PERMISSIONS => self::ADMIN_EDIT_ORDER_PERMISSIONS,
            SalesChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        $delivery = $order->getDeliveries() !== null ? $order->getDeliveries()->first() : null;
        if ($delivery !== null) {
            $options[SalesChannelContextService::SHIPPING_METHOD_ID] = $delivery->getShippingMethodId();
        }

        //get the first not paid transaction or, if all paid, the last transaction
        if ($order->getTransactions() !== null) {
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

        $options = array_merge($options, $overrideOptions);

        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $order->getSalesChannelId(), $options);
        $salesChannelContext->getContext()->addExtensions($context->getExtensions());
        $salesChannelContext->addState(...$context->getStates());
        $salesChannelContext->setTaxState($order->getTaxStatus());

        if ($context->hasState(Context::SKIP_TRIGGER_FLOW)) {
            $salesChannelContext->getContext()->addState(Context::SKIP_TRIGGER_FLOW);
        }

        if ($order->getItemRounding() !== null) {
            $salesChannelContext->setItemRounding($order->getItemRounding());
        }

        if ($order->getTotalRounding() !== null) {
            $salesChannelContext->setTotalRounding($order->getTotalRounding());
        }

        if ($order->getRuleIds() !== null) {
            $salesChannelContext->setRuleIds($order->getRuleIds());
        }

        return $salesChannelContext;
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

            if ($orderDelivery->getPositions() === null) {
                continue;
            }

            foreach ($orderDelivery->getPositions() as $position) {
                if ($position->getOrderLineItem() === null) {
                    continue;
                }

                $identifier = $position->getOrderLineItem()->getIdentifier();

                // line item has been removed and will not be added to delivery
                if ($lineItems->get($identifier) === null) {
                    continue;
                }

                if ($position->getPrice() === null) {
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

            if ($orderDelivery->getShippingMethod() === null
                || $orderDelivery->getShippingOrderAddress() === null
                || $orderDelivery->getShippingOrderAddress()->getCountry() === null
            ) {
                continue;
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
