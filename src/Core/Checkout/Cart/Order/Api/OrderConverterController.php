<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Api;

use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"api"}})
 */
class OrderConverterController extends AbstractController
{
    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @internal
     */
    public function __construct(
        OrderConverter $orderConverter,
        CartPersisterInterface $cartPersister,
        EntityRepository $orderRepository
    ) {
        $this->orderConverter = $orderConverter;
        $this->cartPersister = $cartPersister;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/order/{orderId}/convert-to-cart/", name="api.action.order.convert-to-cart", methods={"POST"})
     */
    public function convertToCart(string $orderId, Context $context): JsonResponse
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->get($orderId);

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $convertedCart = $this->orderConverter->convertToCart($order, $context);

        $this->cartPersister->save(
            $convertedCart,
            $this->orderConverter->assembleSalesChannelContext($order, $context)
        );

        return new JsonResponse(['token' => $convertedCart->getToken()]);
    }
}
