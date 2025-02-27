<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Api;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('checkout')]
class OrderConverterController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly OrderConverter $orderConverter,
        private readonly AbstractCartPersister $cartPersister,
        private readonly EntityRepository $orderRepository
    ) {
    }

    #[Route(path: '/api/_action/order/{orderId}/convert-to-cart/', name: 'api.action.order.convert-to-cart', methods: ['POST'])]
    public function convertToCart(string $orderId, Context $context): JsonResponse
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions.stateMachineState')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();
        if (!$order) {
            throw CartException::orderNotFound($orderId);
        }

        $convertedCart = $this->orderConverter->convertToCart($order, $context);

        $this->cartPersister->save(
            $convertedCart,
            $this->orderConverter->assembleSalesChannelContext($order, $context)
        );

        return new JsonResponse(['token' => $convertedCart->getToken()]);
    }
}
