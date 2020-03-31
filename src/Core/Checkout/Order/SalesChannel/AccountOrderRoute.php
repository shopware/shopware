<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class AccountOrderRoute extends AbstractAccountOrderRoute
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

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        RequestCriteriaBuilder $requestCriteriaBuilder,
        OrderDefinition $salesChannelOrderDefinition
    ) {
        $this->orderRepository = $orderRepository;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->orderDefinition = $salesChannelOrderDefinition;
    }

    public function getDecorated(): AbstractAccountOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/order",
     *      description="Listing orders",
     *      operationId="readOrder",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/order_flat"))
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/order", name="store-api.account.order", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): AccountOrderRouteResponse
    {
        $criteria = new Criteria();
        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->orderDefinition,
            $context->getContext()
        );
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
                        new RangeFilter('createdAt', ['gte' => $latestOrderChange])
                    ]
                )
            );
        }

        return new AccountOrderRouteResponse($this->orderRepository->search($criteria, $context->getContext()));
    }
}
