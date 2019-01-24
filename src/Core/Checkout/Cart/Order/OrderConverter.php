<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Util\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Util\Transformer\CartTransformer;
use Shopware\Core\Checkout\Util\Transformer\CustomerTransformer;
use Shopware\Core\Checkout\Util\Transformer\DeliveryTransformer;
use Shopware\Core\Checkout\Util\Transformer\LineItemTransformer;
use Shopware\Core\Checkout\Util\Transformer\TransactionTransformer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Uuid;

class OrderConverter
{
    public const CART_TYPE = 'recalculation';

    public const ORIGINAL_ID = 'originalId';

    private const LINE_ITEM_PLACEHOLDER = 'lineItemPlaceholder';

    /**
     * @var TaxDetector
     */
    protected $taxDetector;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderLineItemRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderDeliveryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var CheckoutContextFactory
     */
    protected $checkoutContextFactory;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderDeliveryPositionRepository;

    /**
     * @var AddressTransformer
     */
    protected $addressTransformer;

    /**
     * @var CartTransformer
     */
    protected $cartTransformer;

    /**
     * @var CustomerTransformer
     */
    protected $customerTransformer;

    /**
     * @var LineItemTransformer
     */
    protected $lineItemTransformer;

    /**
     * @var TransactionTransformer
     */
    protected $transactionTransformer;
    /**
     * @var DeliveryTransformer
     */
    protected $deliveryTransformer;

    public function __construct(
        TaxDetector $taxDetector,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderLineItemRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $orderDeliveryPositionRepository,
        CheckoutContextFactory $checkoutContextFactory,
        AddressTransformer $addressTransformer,
        CartTransformer $cartTransformer,
        CustomerTransformer $customerTransformer,
        DeliveryTransformer $deliveryTransformer,
        LineItemTransformer $lineItemTransformer,
        TransactionTransformer $transactionTransformer
    ) {
        $this->taxDetector = $taxDetector;
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->customerRepository = $customerRepository;
        $this->orderDeliveryPositionRepository = $orderDeliveryPositionRepository;
        $this->checkoutContextFactory = $checkoutContextFactory;
        $this->addressTransformer = $addressTransformer;
        $this->cartTransformer = $cartTransformer;
        $this->customerTransformer = $customerTransformer;
        $this->deliveryTransformer = $deliveryTransformer;
        $this->lineItemTransformer = $lineItemTransformer;
        $this->transactionTransformer = $transactionTransformer;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     */
    public function convertToOrder(
        Cart $cart,
        CheckoutContext $context,
        bool $includeCustomer = true,
        bool $includeBillingAddress = true,
        bool $includeDeliveries = true,
        bool $includeTransactions = true): array
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
        if ($cart->getLineItems()->count() <= 0) {
            throw new EmptyCartException();
        }

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            if ($delivery->getLocation()->getAddress() !== null || $delivery->hasExtensionOfType(self::ORIGINAL_ID, IdStruct::class)) {
                continue;
            }
            throw new DeliveryWithoutAddressException();
        }
        $data = $this->cartTransformer->transform($cart, $context);

        if ($includeCustomer) {
            $data['orderCustomer'] = $this->customerTransformer->transform($context->getCustomer());
        }

        $convertedLineItems = $this->lineItemTransformer->transformCollection($cart->getLineItems());

        $shippingAddresses = [];

        if ($includeDeliveries) {
            $shippingAddresses = $this->addressTransformer->transformCollection($cart->getDeliveries()->getAddresses(), true);
            $data['deliveries'] = $this->deliveryTransformer->transformCollection(
                $cart->getDeliveries(),
                $convertedLineItems,
                $shippingAddresses
            );
        }

        if ($includeBillingAddress) {
            $customerAddressId = $context->getCustomer()->getActiveBillingAddress()->getId();

            if (array_key_exists($customerAddressId, $shippingAddresses)) {
                $billingAddressId = $shippingAddresses[$customerAddressId]['id'];
            } else {
                $billingAddress = $this->addressTransformer->transform($context->getCustomer()->getActiveBillingAddress());
                $data['addresses'] = [$billingAddress];
                $billingAddressId = $billingAddress['id'];
            }
            $data['billingAddressId'] = $billingAddressId;
        }

        if ($includeTransactions) {
            $data['transactions'] = $this->transactionTransformer->transformCollection($cart->getTransactions());
        }

        $data['lineItems'] = array_values($convertedLineItems);

        /** @var IdStruct|null $idStruct */
        $idStruct = $cart->getExtensionOfType(self::ORIGINAL_ID, IdStruct::class);
        if ($idStruct !== null) {
            $data['id'] = $idStruct->getId();
        }

        return $data;
    }

    /**
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     */
    public function convertToCart(OrderEntity $order, Context $context): Cart
    {
        /** @var OrderLineItemCollection $lineItems */
        $lineItems = $this->getLineItems($order->getId(), $context);
        /** @var OrderDeliveryCollection $deliveries */
        $deliveries = $this->getDeliveries($order->getId(), $context);

        $cart = new Cart(self::CART_TYPE, Uuid::uuid4()->getHex());
        $cart->setPrice($order->getPrice());
        $cart->addExtension(self::ORIGINAL_ID, new IdStruct($order->getId()));
        /* NEXT-708 support:
            - transactions
        */

        $index = [];
        $root = new LineItemCollection();

        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $id => $lineItem) {
            if (!array_key_exists($id, $index)) {
                $index[$id] = new LineItem($lineItem->getIdentifier(), self::LINE_ITEM_PLACEHOLDER);
            }

            /** @var LineItem $currentLineItem */
            $currentLineItem = $index[$id];

            $this->updateLineItem($currentLineItem, $lineItem, $id);

            if ($lineItem->getParentId() === null) {
                $root->add($currentLineItem);
                continue;
            }

            if (!array_key_exists($lineItem->getParentId(), $index)) {
                $index[$lineItem->getParentId()] = new LineItem($lineItem->getParentId(), self::LINE_ITEM_PLACEHOLDER);
            }

            $index[$lineItem->getParentId()]->addChild($currentLineItem);
        }

        $cart->addLineItems($root);
        $cart->setDeliveries($this->convertDeliveries($deliveries, $root));

        return $cart;
    }

    public function assembleCheckoutContext(OrderEntity $order, Context $context): CheckoutContext
    {
        $customerId = $order->getOrderCustomer()->getCustomerId();
        $customerGroupId = null;

        if ($customerId) {
            /** @var CustomerEntity|null $customer */
            $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->get($customerId);
            $customerGroupId = $customer->getGroupId() ?? null;
        }

        return $this->checkoutContextFactory->create(
            Uuid::uuid4()->getHex(),
            $order->getSalesChannelId(),
            [
                CheckoutContextService::CURRENCY_ID => $order->getCurrencyId(),
                CheckoutContextService::PAYMENT_METHOD_ID => $order->getPaymentMethodId(),
                CheckoutContextService::CUSTOMER_ID => $customerId,
                CheckoutContextService::STATE_ID => $order->getStateId(),
                CheckoutContextService::CUSTOMER_GROUP_ID => $customerGroupId,
            ]
        );
    }

    protected function getDeliveries(string $orderId, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        return $this->orderDeliveryRepository->search($criteria, $context)->getEntities();
    }

    protected function getLineItems(string $orderId, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_line_item.orderId', $orderId));

        return $this->orderLineItemRepository->search($criteria, $context)->getEntities();
    }

    protected function convertDeliveries(OrderDeliveryCollection $orderDeliveries, LineItemCollection $lineItems): DeliveryCollection
    {
        $cartDeliveries = new DeliveryCollection();

        /** @var OrderDeliveryEntity $orderDelivery */
        foreach ($orderDeliveries as $orderDelivery) {
            $deliveryDate = new DeliveryDate(
                $orderDelivery->getShippingDateEarliest(),
                $orderDelivery->getShippingDateLatest()
            );

            $deliveryPositions = new DeliveryPositionCollection();

            /** @var OrderDeliveryPositionEntity $position */
            foreach ($orderDelivery->getPositions() as $position) {
                $identifier = $position->getOrderLineItem()->getIdentifier();

                // line item has been removed and will not be added to delivery
                if ($lineItems->get($identifier) === null) {
                    continue;
                }

                $deliveryPositions->add(new DeliveryPosition(
                    $identifier,
                    $lineItems->get($identifier),
                    $position->getPrice()->getQuantity(),
                    $position->getPrice(),
                    $deliveryDate
                ));
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

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws InvalidPayloadException
     */
    protected function updateLineItem(LineItem $lineItem, OrderLineItemEntity $entity, string $id): void
    {
        $lineItem->setKey($entity->getIdentifier())
            ->setType($entity->getType())
            ->setStackable(true)
            ->setQuantity($entity->getQuantity())
            ->setStackable($entity->getStackable())
            ->setLabel($entity->getLabel())
            ->setGood($entity->getGood())
            ->setPriority($entity->getPriority())
            ->setRemovable($entity->getRemovable())
            ->setStackable($entity->getStackable())
            ->addExtension(self::ORIGINAL_ID, new IdStruct($id));

        if ($entity->getPayload() !== null) {
            $lineItem->setPayload($entity->getPayload());
        }

        if ($entity->getPrice() !== null) {
            $lineItem->setPrice($entity->getPrice());
        }

        if ($entity->getPriceDefinition() !== null) {
            $lineItem->setPriceDefinition($entity->getPriceDefinition());
        }
    }
}
