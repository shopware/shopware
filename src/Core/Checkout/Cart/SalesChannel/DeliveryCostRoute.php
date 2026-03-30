<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class DeliveryCostRoute extends AbstractDeliveryCostRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $shippingMethodRepository,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute
    ) {
    }

    public function getDecorated(): AbstractDeliveryCostRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param non-empty-list<string>|null $availableShippingMethodIds
     */
    #[Route(
        path: '/store-api/checkout/delivery-cost/cart',
        name: 'store-api.checkout.delivery-cost.cart',
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function deliveryCostsCart(Cart $cart, SalesChannelContext $salesChannelContext, ?array $availableShippingMethodIds = null): DeliveryCostRouteResponse
    {
        return Profiler::trace('delivery-cost-calculator::cart', function () use ($cart, $salesChannelContext, $availableShippingMethodIds) {
            $deliveries = new DeliveryCostCollection();

            if ($availableShippingMethodIds === null) {
                $availableShippingMethods = $this->checkoutGatewayRoute
                    ->load(new Request(), $cart, $salesChannelContext)
                    ->getShippingMethods();

                $availableShippingMethodIds = (new RuleIdMatcher())->filterCollection($availableShippingMethods, $salesChannelContext->getRuleIds())->getKeys();
                if ($availableShippingMethodIds === []) {
                    return new DeliveryCostRouteResponse($deliveries);
                }
            }

            $shippingMethods = $this->loadShippingMethods($salesChannelContext, $availableShippingMethodIds);
            foreach ($shippingMethods as $shippingMethod) {
                if ($cart->getDeliveries()->has($shippingMethod->getId())) {
                    $delivery = $cart->getDeliveries()->get($shippingMethod->getId());
                } else {
                    $delivery = $this->resolveCartDelivery(
                        $shippingMethod,
                        $salesChannelContext,
                        $cart,
                    );
                }

                if ($delivery !== null) {
                    $deliveries->set($shippingMethod->getId(), new DeliveryCost(
                        $delivery->getShippingCosts(),
                        $delivery->getDeliveryDate(),
                        $shippingMethod,
                    ));
                }
            }

            return new DeliveryCostRouteResponse($deliveries);
        });
    }

    /**
     * @param non-empty-list<string> $shippingMethodIds
     */
    private function loadShippingMethods(SalesChannelContext $context, array $shippingMethodIds): ShippingMethodCollection
    {
        $criteria = (new Criteria($shippingMethodIds))
            ->addAssociations(['deliveryTime', 'tax'])
            ->setTitle('cart::shipping-methods');

        $criteria->getAssociation('prices')
            ->addFilter(new EqualsAnyFilter('ruleId', [null, ...$context->getRuleIds()]));

        return $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function resolveCartDelivery(
        ShippingMethodEntity $shippingMethod,
        SalesChannelContext $salesChannelContext,
        Cart $originalCart,
    ): ?Delivery {
        $clonedContext = clone $salesChannelContext;
        $cart = clone $originalCart;

        // Setting data to avoid loading them twice - and separate
        $cart->getData()->set('shipping-method-' . $shippingMethod->getId(), $shippingMethod);
        $clonedContext->assign(['shippingMethod' => $shippingMethod]);

        $behavior = [
            ...$salesChannelContext->getPermissions(),
            CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
        ];

        return $this->cartRuleLoader->loadByCart($clonedContext, $cart, new CartBehavior($behavior), true)
            ->getCart()
            ->getDeliveries()->first();
    }
}
