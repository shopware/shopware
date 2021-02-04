<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CartOrderRoute extends AbstractCartOrderRoute
{
    /**
     * @var CartCalculator
     */
    private $cartCalculator;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderPersisterInterface
     */
    private $orderPersister;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderCustomerRepository;

    public function __construct(
        CartCalculator $cartCalculator,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderCustomerRepository,
        OrderPersisterInterface $orderPersister,
        CartPersisterInterface $cartPersister,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cartCalculator = $cartCalculator;
        $this->orderRepository = $orderRepository;
        $this->orderPersister = $orderPersister;
        $this->cartPersister = $cartPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderCustomerRepository = $orderCustomerRepository;
    }

    public function getDecorated(): AbstractCartOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @OA\Post(
     *      path="/checkout/order",
     *      summary="Create a new order from cart",
     *      operationId="createOrder",
     *      tags={"Store API", "Cart"},
     *      @OA\Response(
     *          response="200",
     *          description="Order",
     *          @OA\JsonContent(ref="#/components/schemas/order_flat")
     *     )
     * )
     * @LoginRequired(allowGuest=true)
     * @Route("/store-api/checkout/order", name="store-api.checkout.cart.order", methods={"POST"})
     */
    public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): CartOrderRouteResponse
    {
        $calculatedCart = $this->cartCalculator->calculate($cart, $context);

        $this->addCustomerComment($calculatedCart, $data);
        $this->addAffiliateTracking($calculatedCart, $data);

        $orderId = $this->orderPersister->persist($calculatedCart, $context);

        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('lineItems')
            ->addAssociation('currency')
            ->addAssociation('addresses.country');

        /** @var OrderEntity|null $orderEntity */
        $orderEntity = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$orderEntity) {
            throw new InvalidOrderException($orderId);
        }

        // todo@dr: can be merged with above criteria after NEXT-4466
        $orderEntity->setOrderCustomer(
            $this->fetchCustomer($orderEntity->getId(), $context->getContext())
        );

        $orderPlacedEvent = new CheckoutOrderPlacedEvent(
            $context->getContext(),
            $orderEntity,
            $context->getSalesChannel()->getId()
        );

        $this->eventDispatcher->dispatch($orderPlacedEvent);

        $this->cartPersister->delete($context->getToken(), $context);

        return new CartOrderRouteResponse($orderEntity);
    }

    private function fetchCustomer(string $orderId, Context $context): OrderCustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addAssociation('customer');
        $criteria->addAssociation('salutation');

        return $this->orderCustomerRepository
            ->search($criteria, $context)
            ->first();
    }

    private function addCustomerComment(Cart $cart, DataBag $data): void
    {
        $customerComment = ltrim(rtrim((string) $data->get(OrderService::CUSTOMER_COMMENT_KEY, '')));

        if ($customerComment === '') {
            return;
        }

        $cart->setCustomerComment($customerComment);
    }

    private function addAffiliateTracking(Cart $cart, DataBag $data): void
    {
        $affiliateCode = $data->get(OrderService::AFFILIATE_CODE_KEY);
        $campaignCode = $data->get(OrderService::CAMPAIGN_CODE_KEY);
        if ($affiliateCode) {
            $cart->setAffiliateCode($affiliateCode);
        }

        if ($campaignCode) {
            $cart->setCampaignCode($campaignCode);
        }
    }
}
