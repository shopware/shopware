<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    public function __construct(
        CartCalculator $cartCalculator,
        EntityRepositoryInterface $orderRepository,
        OrderPersisterInterface $orderPersister,
        CartPersisterInterface $cartPersister,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cartCalculator = $cartCalculator;
        $this->orderRepository = $orderRepository;
        $this->orderPersister = $orderPersister;
        $this->cartPersister = $cartPersister;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractCartOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @OA\Post(
     *      path="/checkout/order",
     *      summary="Create an order from a cart",
     *      description="Creates a new order from the current cart and deletes the cart.",
     *      operationId="createOrder",
     *      tags={"Store API", "Order"},
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="customerComment",
     *                  description="Adds a comment from the customer to the order.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="affiliateCode",
     *                  description="The affiliate code can be used to track which referrer the customer came through. An example could be `Price-comparison-company-XY`.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="campaignCode",
     *                  description="The campaign code is used to track which action the customer came from. An example could be `Summer-Deals`",
     *                  type="string"
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Order",
     *          @OA\JsonContent(ref="#/components/schemas/Order")
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
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('orderCustomer.salutation')
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

        $orderPlacedEvent = new CheckoutOrderPlacedEvent(
            $context->getContext(),
            $orderEntity,
            $context->getSalesChannel()->getId()
        );

        $this->eventDispatcher->dispatch($orderPlacedEvent);

        $this->cartPersister->delete($context->getToken(), $context);

        return new CartOrderRouteResponse($orderEntity);
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
