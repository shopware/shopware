<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use DateTime;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;

class OrderConverter
{
    public const CART_TYPE = 'recalculation';
    private const LINE_ITEM_PLACEHOLDER = 'line_item_placeholder';

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderLineItemRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CheckoutContextFactory
     */
    private $checkoutContextFactory;

    public function __construct(
        TaxDetector $taxDetector,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderLineItemRepository,
        EntityRepositoryInterface $customerRepository,
        CheckoutContextFactory $checkoutContextFactory
    ) {
        $this->taxDetector = $taxDetector;
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->customerRepository = $customerRepository;
        $this->checkoutContextFactory = $checkoutContextFactory;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     */
    public function convertToOrder(Cart $cart, CheckoutContext $context): array
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
        if ($cart->getLineItems()->count() <= 0) {
            throw new EmptyCartException();
        }

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            if (!$delivery->getLocation()->getAddress()) {
                throw new DeliveryWithoutAddressException();
            }
        }
        $addressId = Uuid::uuid4()->getHex();
        $data = $this->convertCart($cart, $context, $addressId);

        $data['orderCustomer'] = $this->convertCustomer($context);

        $address = $context->getCustomer()->getActiveBillingAddress();
        $data['billingAddress'] = $this->convertAddress($address);
        $data['billingAddress']['id'] = $addressId;

        $convertedLineItems = $this->convertLineItems($cart->getLineItems());

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            $data['deliveries'][] = $this->convertDelivery($delivery, $convertedLineItems);
        }

        foreach ($cart->getTransactions() as $transaction) {
            $data['transactions'][] = $this->convertTransaction($transaction);
        }

        $data['lineItems'] = array_values($convertedLineItems);

        return $data;
    }

    /**
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     * @throws InvalidPayloadException
     * @throws LineItemNotStackableException
     */
    public function convertToCart(OrderEntity $order, Context $context): Cart
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_line_item.orderId', $order->getId()));

        $lineItems = $this->orderLineItemRepository->search($criteria, $context);

        $cart = new Cart(self::CART_TYPE, Uuid::uuid4()->getHex());

        $cart->setPrice(new CartPrice(
            $order->getAmountNet(),
            $order->getAmountTotal(),
            $order->getPositionPrice(),
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $this->getTaxStatus($order)
        ));

        /* NEXT-708 support:
            - cart: calculated tax and tax rule collection
            - line item: price + price definition currently rely on serialized data
            - transactions
            - deliveries
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

            $currentLineItem
            ->setKey($lineItem->getIdentifier())
                ->setType($lineItem->getType())
                ->setStackable(true)
                ->setQuantity($lineItem->getQuantity())
                ->setStackable($lineItem->getStackable())
                ->setLabel($lineItem->getLabel())
                ->setGood($lineItem->getGood())
                ->setPriority($lineItem->getPriority())
                ->setRemovable($lineItem->getRemovable())
                ->setStackable($lineItem->getStackable());

            if ($lineItem->getPayload() !== null) {
                $currentLineItem->setPayload($lineItem->getPayload());
            }

            if ($lineItem->getPrice() !== null) {
                $currentLineItem->setPrice($lineItem->getPrice());
            }

            if ($lineItem->getPriceDefinition() !== null) {
                $currentLineItem->setPriceDefinition($lineItem->getPriceDefinition());
            }

            if ($lineItem->getParentId() !== null) {
                if (!array_key_exists($lineItem->getParentId(), $index)) {
                    $index[$lineItem->getParentId()] = new LineItem($lineItem->getParentId(), self::LINE_ITEM_PLACEHOLDER);
                }

                $index[$lineItem->getParentId()]->addChild($currentLineItem);
            } else {
                $root->add($currentLineItem);
            }
        }
        $cart->addLineItems($root);

        return $cart;
    }

    public function assembleCheckoutContext(OrderEntity $order, Context $context): CheckoutContext
    {
        $customerId = $order->getOrderCustomer()->getCustomerId();
        $customerGroupId = null;

        if ($customerId) {
            /** @var CustomerEntity|null $customer */
            $customer = $this->customerRepository->read(new ReadCriteria([$customerId]), $context)->get($customerId);
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

    private function getTaxStatus(OrderEntity $order): string
    {
        if ($order->getIsTaxFree()) {
            return CartPrice::TAX_STATE_FREE;
        }

        return $order->getIsNet() ? CartPrice::TAX_STATE_NET : CartPrice::TAX_STATE_GROSS;
    }

    private function convertAddress(CustomerAddressEntity $address): array
    {
        return array_filter([
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'salutation' => $address->getSalutation(),
            'title' => $address->getTitle(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'vatId' => $address->getVatId(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
        ]);
    }

    private function convertDelivery(Delivery $delivery, array $lineItems): array
    {
        $deliveryData = [
            'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::DATE_FORMAT),
            'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::DATE_FORMAT),
            'shippingMethodId' => $delivery->getShippingMethod()->getId(),
            'shippingAddress' => $this->convertAddress($delivery->getLocation()->getAddress()),
            'orderStateId' => Defaults::ORDER_STATE_OPEN,
            'positions' => [],
        ];

        /** @var DeliveryPosition $position */
        foreach ($delivery->getPositions() as $position) {
            $positionPrice = $position->getPrice();
            $deliveryData['positions'][] = [
                'unitPrice' => $positionPrice->getUnitPrice(),
                'totalPrice' => $positionPrice->getTotalPrice(),
                'quantity' => $position->getQuantity(),
                'orderLineItemId' => $lineItems[$position->getIdentifier()]['id'],
            ];
        }

        return $deliveryData;
    }

    private function convertTransaction(Transaction $transaction): array
    {
        return [
            'paymentMethodId' => $transaction->getPaymentMethodId(),
            'amount' => $transaction->getAmount(),
            'orderTransactionStateId' => Defaults::ORDER_TRANSACTION_OPEN,
        ];
    }

    private function convertLineItems(LineItemCollection $lineItems, ?string $parentId = null)
    {
        $converted = [];
        foreach ($lineItems as $lineItem) {
            $id = Uuid::uuid4()->getHex();

            $data = [
                'id' => $id,
                'identifier' => $lineItem->getKey(),
                'quantity' => $lineItem->getQuantity(),
                'unitPrice' => $lineItem->getPrice()->getUnitPrice(),
                'totalPrice' => $lineItem->getPrice()->getTotalPrice(),
                'type' => $lineItem->getType(),
                'label' => $lineItem->getLabel(),
                'description' => $lineItem->getDescription(),
                'priority' => $lineItem->getPriority(),
                'good' => $lineItem->isGood(),
                'removable' => $lineItem->isRemovable(),
                'stackable' => $lineItem->isStackable(),
                'price' => $lineItem->getPrice(),
                'priceDefinition' => $lineItem->getPriceDefinition(),
                'parentId' => $parentId,
                'payload' => $lineItem->getPayload(),
            ];

            $converted[$lineItem->getKey()] = array_filter($data, function ($value) {
                return $value !== null;
            });

            if ($lineItem->hasChildren()) {
                $converted = array_merge($converted, $this->convertLineItems($lineItem->getChildren(), $id));
            }
        }

        return $converted;
    }

    private function convertCart(Cart $cart, CheckoutContext $context, string $addressId): array
    {
        $cartPrice = $cart->getPrice();
        $cartShippingCosts = $cart->getShippingCosts();
        $cartShippingCostsTotalPrice = $cartShippingCosts->getTotalPrice();
        $currency = $context->getCurrency();
        $deepLinkCode = Random::getBase64UrlString(32);

        return [
            'id' => Uuid::uuid4()->getHex(),
            'date' => (new DateTime())->format(Defaults::DATE_FORMAT),
            'amountTotal' => $cartPrice->getTotalPrice(),
            'amountNet' => $cartPrice->getNetPrice(),
            'positionPrice' => $cartPrice->getPositionPrice(),
            'shippingTotal' => $cartShippingCostsTotalPrice,
            'shippingNet' => $cartShippingCostsTotalPrice - $cartShippingCosts->getCalculatedTaxes()->getAmount(),
            'isNet' => !$this->taxDetector->useGross($context),
            'isTaxFree' => $this->taxDetector->isNetDelivery($context),
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => $context->getPaymentMethod()->getId(),
            'currencyId' => $currency->getId(),
            'currencyFactor' => $currency->getFactor(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'billingAddressId' => $addressId,
            'lineItems' => [],
            'deliveries' => [],
            'deepLinkCode' => $deepLinkCode,
        ];
    }

    private function convertCustomer(CheckoutContext $context): array
    {
        $customer = $context->getCustomer();

        return [
            'customerId' => $customer->getId(),
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'salutation' => $customer->getSalutation(),
            'title' => $customer->getTitle(),
            'customerNumber' => $customer->getCustomerNumber(),
        ];
    }
}
