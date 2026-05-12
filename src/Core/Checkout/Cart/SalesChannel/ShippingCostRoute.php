<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingCost;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
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
use Shopware\Core\PlatformRequest;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class ShippingCostRoute extends AbstractShippingCostRoute
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

    public function getDecorated(): AbstractShippingCostRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Calculates shipping costs for the current cart and the requested shipping methods.
     *
     * This route can be expensive because alternative shipping methods require separate cart recalculations.
     * Only call it when shipping costs are actually needed and prefer adding a cache layer for repeated requests.
     *
     * @param non-empty-list<string>|null $availableShippingMethodIds
     */
    #[Route(
        path: '/store-api/shipping-cost/cart',
        name: 'store-api.shipping-cost.cart',
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function shippingCostsCart(Cart $cart, SalesChannelContext $salesChannelContext, ?array $availableShippingMethodIds = null): ShippingCostRouteResponse
    {
        return Profiler::trace('shipping-cost-calculator::cart', function () use ($cart, $salesChannelContext, $availableShippingMethodIds) {
            $shippingCosts = new ShippingCostCollection();

            if ($availableShippingMethodIds === null) {
                $request = new Request();
                $request->request->set('onlyAvailable', true);

                $availableShippingMethodIds = $this->checkoutGatewayRoute
                    ->load($request, $cart, $salesChannelContext)
                    ->getShippingMethods()
                    ->getKeys();

                if ($availableShippingMethodIds === []) {
                    return new ShippingCostRouteResponse($shippingCosts);
                }
            }

            $shippingMethods = $this->loadShippingMethods($salesChannelContext, $availableShippingMethodIds);
            foreach ($shippingMethods as $shippingMethod) {
                if ($shippingMethod->getId() === $salesChannelContext->getShippingMethod()->getId()) {
                    $deliveries = $cart->getDeliveries();
                } else {
                    $deliveries = $this->resolveCartDeliveries(
                        $shippingMethod,
                        $salesChannelContext,
                        $cart,
                    );
                }

                $delivery = $deliveries->getPrimaryDelivery(null);
                if ($delivery !== null) {
                    $shippingCosts->set($shippingMethod->getId(), new ShippingCost(
                        $deliveries->getShippingCosts()->sum(),
                        $delivery->getDeliveryDate(),
                        $shippingMethod,
                    ));
                }
            }

            return new ShippingCostRouteResponse($shippingCosts);
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

    private function resolveCartDeliveries(
        ShippingMethodEntity $shippingMethod,
        SalesChannelContext $salesChannelContext,
        Cart $originalCart,
    ): DeliveryCollection {
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
            ->getDeliveries();
    }
}
