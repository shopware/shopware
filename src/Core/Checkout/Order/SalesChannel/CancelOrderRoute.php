<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class CancelOrderRoute extends AbstractCancelOrderRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OrderService $orderService,
        private readonly EntityRepository $orderRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function getDecorated(): AbstractCancelOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/order/state/cancel', name: 'store-api.order.state.cancel', methods: ['POST'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true])]
    public function cancel(Request $request, SalesChannelContext $context): CancelOrderRouteResponse
    {
        if (!$this->systemConfigService->getBool('core.cart.enableOrderRefunds', $context->getSalesChannelId())) {
            throw OrderException::orderNotCancellable();
        }

        $orderId = RequestParamHelper::get($request, 'orderId');

        if (!$orderId) {
            throw RoutingException::invalidRequestParameter('orderId');
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
        if (!$context->getCustomer()) {
            throw OrderException::customerNotLoggedIn();
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addFilter(new EqualsFilter('orderCustomer.customerId', $context->getCustomerId()));

        if ($this->orderRepository->searchIds($criteria, $context->getContext())->firstId() === null) {
            throw OrderException::orderNotFound($orderId);
        }
    }
}
