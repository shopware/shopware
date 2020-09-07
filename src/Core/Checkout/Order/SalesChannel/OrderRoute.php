<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class OrderRoute extends AbstractOrderRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    /**
     * @var OrderDefinition
     */
    private $orderDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $promotionRepository;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        RequestCriteriaBuilder $requestCriteriaBuilder,
        OrderDefinition $salesChannelOrderDefinition,
        EntityRepositoryInterface $promotionRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->orderDefinition = $salesChannelOrderDefinition;
        $this->promotionRepository = $promotionRepository;
    }

    public function getDecorated(): AbstractOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Entity("order")
     * @OA\Post(
     *      path="/order",
     *      description="Listing orders",
     *      operationId="readOrder",
     *      tags={"Store API", "Order"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="checkPromotion",
     *          in="get",
     *          required=false,
     *          description="wether to check the Promotions of orders",
     *          @OA\Schema(
     *              type="boolean"
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/order_flat"))
     *     )
     * )
     * @Route(path="/store-api/v{version}/order", name="store-api.order", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): OrderRouteResponse
    {
        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->orderDefinition, $context->getContext());
        }

        if ($context->getCustomer()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomer()->getId()));
        } elseif (!$criteria->hasEqualsFilter('order.deepLinkCode')) {
            throw new CustomerNotLoggedInException();
        } else {
            // Search with deepLinkCode needs updatedAt Filter
            $latestOrderDate = (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))->modify(-abs(30) . ' Day');
            $latestOrderChange = $latestOrderDate->format('Y-m-d H:i:s');
            $criteria->addFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new RangeFilter('updatedAt', ['gte' => $latestOrderChange]),
                        new RangeFilter('createdAt', ['gte' => $latestOrderChange]),
                    ]
                )
            );
        }

        $orders = $this->orderRepository->search($criteria, $context->getContext());

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
                $promotionIds[] = $payload['promotionId'];
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
}
