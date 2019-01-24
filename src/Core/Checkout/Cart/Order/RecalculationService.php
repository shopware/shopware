<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\OrderRecalculationException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Util\Transformer\AddressTransformer;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Exception\AccountAddress\AddressNotFoundException;

class RecalculationService
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderConverter
     */
    protected $orderConverter;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderAddressRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $customerAddressRepository;

    /**
     * @var AddressTransformer
     */
    protected $addressTransformer;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        OrderConverter $orderConverter,
        CartService $cartService,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $orderAddressRepository,
        EntityRepositoryInterface $customerAddressRepository,
        AddressTransformer $addressTransformer
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderConverter = $orderConverter;
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->addressTransformer = $addressTransformer;
    }

    /**
     * @throws InvalidOrderException
     * @throws OrderRecalculationException
     * @throws CustomerNotLoggedInException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function recalculateOrder(string $orderId, Context $context): void
    {
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->get($orderId);
        $this->validateOrder($order, $orderId);

        $checkoutContext = $this->orderConverter->assembleCheckoutContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);
        $recalculatedCart = $this->cartService->refresh($cart, $checkoutContext);

        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $checkoutContext, false, false, true, false);
        $orderData['id'] = $order->getId();
        $this->orderRepository->upsert([$orderData], $context);
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     * @throws InvalidOrderException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws OrderRecalculationException
     * @throws ProductNotFoundException
     */
    public function addProductToOrder(string $orderId, string $productId, int $quantity, Context $context)
    {
        $lineItem = (new LineItem($productId, ProductCollector::LINE_ITEM_TYPE, $quantity))
            ->setRemovable(true)
            ->setStackable(true)
            ->setPayload(['id' => $productId]);

        $this->addCustomLineItem($orderId, $lineItem, $context);
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     * @throws InvalidOrderException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws OrderRecalculationException
     * @throws InconsistentCriteriaIdsException
     */
    public function addCustomLineItem(string $orderId, LineItem $lineItem, Context $context)
    {
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->get($orderId);
        $this->validateOrder($order, $orderId);

        $checkoutContext = $this->orderConverter->assembleCheckoutContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context);

        $recalculatedCart = $this->cartService->add($cart, $lineItem, $checkoutContext);

        $orderData = $this->orderConverter->convertToOrder($recalculatedCart, $checkoutContext, false, false, false, false);
        $orderData['id'] = $order->getId();
        $this->orderRepository->upsert([$orderData], $context);
    }

    /**
     * @throws AddressNotFoundException
     * @throws OrderRecalculationException
     * @throws InconsistentCriteriaIdsException
     */
    public function replaceOrderAddressWithCustomerAddress(string $orderAddressId, string $customerAddressId, Context $context)
    {
        $this->validateOrderAddress($orderAddressId, $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customer_address.id', $customerAddressId));
        $customerAddress = $this->customerAddressRepository->search($criteria, $context)->get($customerAddressId);
        if ($customerAddress === null) {
            throw new AddressNotFoundException($customerAddressId);
        }

        $newOrderAddress = $this->addressTransformer->transform($customerAddress);
        $newOrderAddress['id'] = $orderAddressId;
        $this->orderAddressRepository->upsert([$newOrderAddress], $context);
    }

    /**
     * @throws OrderRecalculationException
     * @throws InvalidOrderException
     */
    private function validateOrder(?OrderEntity $order, string $orderId): void
    {
        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $this->checkVersion($order);
    }

    /**
     * @throws ProductNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateProduct(string $productId, Context $context): void
    {
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->get($productId);

        if (!$product) {
            throw new ProductNotFoundException($productId);
        }
    }

    /**
     * @throws OrderRecalculationException
     */
    private function checkVersion(Entity $entity)
    {
        if ($entity->getVersionId() === Defaults::LIVE_VERSION) {
            throw new OrderRecalculationException(
                $entity->getUniqueIdentifier(),
                'Live versions can\'t be recalculated. Please create a new version.'
            );
        }
    }

    /**
     * @throws AddressNotFoundException
     * @throws OrderRecalculationException
     * @throws InconsistentCriteriaIdsException
     */
    private function validateOrderAddress(string $orderAddressId, Context $context)
    {
        $address = $this->orderAddressRepository->search(new Criteria([$orderAddressId]), $context)->get($orderAddressId);
        if (!$address) {
            throw new AddressNotFoundException($orderAddressId);
        }

        $this->checkVersion($address);
    }
}
