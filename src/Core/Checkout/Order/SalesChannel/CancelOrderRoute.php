<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CancelOrderRoute extends AbstractCancelOrderRoute
{
    private OrderService $orderService;

    private EntityRepositoryInterface $orderRepository;

    /**
     * @internal
     */
    public function __construct(OrderService $orderService, EntityRepositoryInterface $orderRepository)
    {
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
    }

    public function getDecorated(): AbstractCancelOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
    * @Since("6.2.0.0")
    * @Route(path="/store-api/order/state/cancel", name="store-api.order.state.cancel", methods={"POST"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
    */
    public function cancel(Request $request, SalesChannelContext $context): CancelOrderRouteResponse
    {
        $orderId = $request->get('orderId', null);

        if ($orderId === null) {
            throw new InvalidRequestParameterException('orderId');
        }

        $this->verify($orderId, $context);

        $newState = $this->orderService->orderStateTransition(
            $orderId,
            'cancel',
            new ParameterBag(),
            $context->getContext()
        );

        return new CancelOrderRouteResponse($newState);
    }

    private function verify(string $orderId, SalesChannelContext $context): void
    {
        if ($context->getCustomer() === null) {
            throw CartException::customerNotLoggedIn();
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $context->getCustomer()->getId()));

        if ($this->orderRepository->searchIds($criteria, $context->getContext())->firstId() === null) {
            throw new EntityNotFoundException('order', $orderId);
        }
    }
}
