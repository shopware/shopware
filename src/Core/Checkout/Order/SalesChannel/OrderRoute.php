<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class OrderRoute extends AbstractOrderRoute
{
    private EntityRepositoryInterface $orderRepository;

    private EntityRepositoryInterface $promotionRepository;

    private RateLimiter $rateLimiter;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $promotionRepository,
        RateLimiter $rateLimiter
    ) {
        $this->orderRepository = $orderRepository;
        $this->promotionRepository = $promotionRepository;
        $this->rateLimiter = $rateLimiter;
    }

    public function getDecorated(): AbstractOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("order")
     * @OA\Post(
     *      path="/order",
     *      summary="Fetch a list of orders",
     *      description="List orders of a customer.",
     *      operationId="readOrder",
     *      tags={"Store API", "Order"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="checkPromotion",
     *                  description="Check if the payment method of the order is still changeable.",
     *                  type="boolean"
     *              ),
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="An array of orders and an indicator if the payment of the order can be changed.",
     *          @OA\JsonContent(ref="#/components/schemas/OrderRouteResponse")
     *     )
     * )
     * @Route(path="/store-api/order", name="store-api.order", methods={"GET", "POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws GuestNotAuthenticatedException
     * @throws WrongGuestCredentialsException
     * @throws CustomerAuthThrottledException
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('order.salesChannelId', $context->getSalesChannel()->getId()));

        $criteria->getAssociation('documents')
            ->addFilter(new EqualsFilter('config.displayInCustomerAccount', 'true'))
            ->addFilter(new EqualsFilter('sent', true));

        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('orderCustomer.customer');

        /** @var EqualsFilter|null $deepLinkFilter */
        $deepLinkFilter = \current(array_filter($criteria->getFilters(), static function (Filter $filter) {
            return \in_array('order.deepLinkCode', $filter->getFields(), true)
                || \in_array('deepLinkCode', $filter->getFields(), true);
        })) ?: null;

        if ($context->getCustomer()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        } elseif ($deepLinkFilter === null) {
            throw new CustomerNotLoggedInException();
        }

        $orders = $this->orderRepository->search($criteria, $context->getContext());

        if ($deepLinkFilter !== false) {
            $orders = $this->filterOldOrders($orders);
        }

        // Handle guest authentication if deeplink is set
        if (!$context->getCustomer() && $deepLinkFilter !== null) {
            if (Feature::isActive('FEATURE_NEXT_13795')) {
                try {
                    $cacheKey = strtolower($deepLinkFilter->getValue()) . '-' . $request->getClientIp();

                    $this->rateLimiter->ensureAccepted(RateLimiter::GUEST_LOGIN, $cacheKey);
                } catch (RateLimitExceededException $exception) {
                    throw new CustomerAuthThrottledException($exception->getWaitTime(), $exception);
                }
            }

            /** @var OrderEntity|null $order */
            $order = $orders->first();
            $this->checkGuestAuth($order, $request);
        }

        if (Feature::isActive('FEATURE_NEXT_13795') && isset($cacheKey)) {
            $this->rateLimiter->reset(RateLimiter::GUEST_LOGIN, $cacheKey);
        }

        $response = new OrderRouteResponse($orders);
        if ($request->get('checkPromotion') === true) {
            /** @var OrderEntity $order */
            foreach ($orders as $order) {
                $promotions = $this->getActivePromotions($order, $context);
                $changeable = true;
                foreach ($promotions as $promotion) {
                    $changeable = $this->checkPromotion($promotion);
                    if ($changeable === true) {
                        break;
                    }
                }
                $response->addPaymentChangeable([$order->getId() => $changeable]);
            }
        }

        return $response;
    }

    private function getActivePromotions(OrderEntity $order, SalesChannelContext $context): PromotionCollection
    {
        $promotionIds = [];
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $payload = $lineItem->getPayload();
            if (isset($payload['promotionId']) && $payload['promotionId'] !== null) {
                $promotionIds[] = (string) $payload['promotionId'];
            }
        }

        $promotions = new PromotionCollection();

        if (!empty($promotionIds)) {
            $criteria = new Criteria($promotionIds);
            $criteria->addAssociation('cartRules');
            /** @var PromotionCollection $promotions */
            $promotions = $this->promotionRepository->search($criteria, $context->getContext())->getEntities();
        }

        return $promotions;
    }

    private function checkRuleType(Container $rule): bool
    {
        foreach ($rule->getRules() as $nestedRule) {
            if ($nestedRule instanceof Container && $this->checkRuleType($nestedRule) === false) {
                return false;
            }
            if ($nestedRule instanceof PaymentMethodRule) {
                return false;
            }
        }

        return true;
    }

    private function checkPromotion(PromotionEntity $promotion): bool
    {
        foreach ($promotion->getCartRules() as $cartRule) {
            if ($this->checkCartRule($cartRule) === false) {
                return false;
            }
        }

        return true;
    }

    private function checkCartRule(RuleEntity $cartRule): bool
    {
        $payload = $cartRule->getPayload();
        foreach ($payload->getRules() as $rule) {
            if ($this->checkRuleType($rule) === false) {
                return false;
            }
        }

        return true;
    }

    private function filterOldOrders(EntitySearchResult $orders): EntitySearchResult
    {
        // Search with deepLinkCode needs updatedAt Filter
        $latestOrderDate = (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))->modify(-abs(30) . ' Day');
        $orders = $orders->filter(function (OrderEntity $order) use ($latestOrderDate) {
            return $order->getCreatedAt() > $latestOrderDate || $order->getUpdatedAt() > $latestOrderDate;
        });

        return $orders;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws WrongGuestCredentialsException
     * @throws GuestNotAuthenticatedException
     */
    private function checkGuestAuth(?OrderEntity $order, Request $request): void
    {
        if ($order === null) {
            throw new GuestNotAuthenticatedException();
        }

        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            throw new CustomerNotLoggedInException();
        }

        $guest = $orderCustomer->getCustomer() !== null && $orderCustomer->getCustomer()->getGuest();
        // Throw exception when customer is not guest
        if (!$guest) {
            throw new CustomerNotLoggedInException();
        }

        // Verify email and zip code with this order
        if ($request->get('email', false) && $request->get('zipcode', false)) {
            $billingAddress = $order->getBillingAddress();
            if ($billingAddress === null
                || $request->get('email') !== $orderCustomer->getEmail()
                || $request->get('zipcode') !== $billingAddress->getZipcode()) {
                throw new WrongGuestCredentialsException();
            }
        } else {
            throw new GuestNotAuthenticatedException();
        }
    }
}
