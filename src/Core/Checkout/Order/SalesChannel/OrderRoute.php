<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Order\Event\OrderCriteriaEvent;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class OrderRoute extends AbstractOrderRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<PromotionCollection> $promotionRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $promotionRepository,
        private readonly RateLimiter $rateLimiter,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/order', name: 'store-api.order', methods: ['GET', 'POST'], defaults: ['_entity' => 'order'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        ReplicaConnection::ensurePrimary();

        $criteria->addFilter(new EqualsFilter('order.salesChannelId', $context->getSalesChannel()->getId()));

        $criteria->getAssociation('documents')
            ->addFilter(new EqualsFilter('config.displayInCustomerAccount', 'true'))
            ->addFilter(new EqualsFilter('sent', true));

        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('orderCustomer.customer');

        $deepLinkFilter = \current(array_filter($criteria->getFilters(), static fn (Filter $filter) => \in_array('order.deepLinkCode', $filter->getFields(), true)
            || \in_array('deepLinkCode', $filter->getFields(), true))) ?: null;

        if ($context->getCustomer()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        } elseif ($deepLinkFilter === null) {
            throw CartException::customerNotLoggedIn();
        }

        $this->eventDispatcher->dispatch(new OrderCriteriaEvent($criteria, $context));

        $orderResult = $this->orderRepository->search($criteria, $context->getContext());
        $orders = $orderResult->getEntities();

        // remove old orders only if there is a deeplink filter
        if ($deepLinkFilter !== null) {
            $orders = $this->filterOldOrders($orders);
        }

        // Handle guest authentication if deeplink is set
        if (!$context->getCustomer() && $deepLinkFilter instanceof EqualsFilter) {
            try {
                $cacheKey = strtolower((string) $deepLinkFilter->getValue()) . '-' . $request->getClientIp();

                $this->rateLimiter->ensureAccepted(RateLimiter::GUEST_LOGIN, $cacheKey);
            } catch (RateLimitExceededException $exception) {
                throw OrderException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }

            $order = $orders->first();
            $this->checkGuestAuth($order, $request);
        }

        if (isset($cacheKey)) {
            $this->rateLimiter->reset(RateLimiter::GUEST_LOGIN, $cacheKey);
        }

        $response = new OrderRouteResponse($orderResult);
        if ($request->get('checkPromotion') === true) {
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
            if (isset($payload['promotionId']) && \is_string($payload['promotionId'])) {
                $promotionIds[] = $payload['promotionId'];
            }
        }

        $promotions = new PromotionCollection();

        if (!empty($promotionIds)) {
            $criteria = new Criteria($promotionIds);
            $criteria->addAssociation('cartRules');
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
        if ($promotion->getCartRules() === null) {
            return true;
        }

        foreach ($promotion->getCartRules() as $cartRule) {
            if (!$this->checkCartRule($cartRule)) {
                return false;
            }
        }

        return true;
    }

    private function checkCartRule(RuleEntity $cartRule): bool
    {
        $payload = $cartRule->getPayload();
        if (!$payload instanceof Container) {
            return true;
        }

        foreach ($payload->getRules() as $rule) {
            if ($rule instanceof Container && $this->checkRuleType($rule) === false) {
                return false;
            }
        }

        return true;
    }

    private function filterOldOrders(OrderCollection $orders): OrderCollection
    {
        // Search with deepLinkCode needs updatedAt Filter
        $latestOrderDate = (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))->modify(-abs(30) . ' Day');

        return $orders->filter(fn (OrderEntity $order) => $order->getCreatedAt() > $latestOrderDate || $order->getUpdatedAt() > $latestOrderDate);
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
            throw CartException::customerNotLoggedIn();
        }

        $guest = $orderCustomer->getCustomer() !== null && $orderCustomer->getCustomer()->getGuest();
        // Throw exception when customer is not guest
        if (!$guest) {
            throw CartException::customerNotLoggedIn();
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
