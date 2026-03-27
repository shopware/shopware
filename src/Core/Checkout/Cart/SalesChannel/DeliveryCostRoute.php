<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryProcessor;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\Cart\ProductGatewayInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
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
        private readonly ProductGatewayInterface $productGateway,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly Processor $processor,
        private readonly CartService $cartService,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute
    ) {
    }

    public function getDecorated(): AbstractDeliveryCostRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/checkout/delivery-cost/{productId}',
        name: 'store-api.checkout.delivery-cost.product',
        requirements: ['productId' => Uuid::VALID_PATTERN],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function deliveryCostsByProduct(Request $request, string $productId, SalesChannelContext $salesChannelContext): DeliveryCostRouteResponse
    {
        return Profiler::trace('delivery-cost-calculator::product', function () use ($request, $productId, $salesChannelContext) {
            $clonedContext = clone $salesChannelContext;
            $product = $this->validateProductId($productId, $clonedContext);

            $cart = (new Cart(Uuid::randomHex()))
                ->add(new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));

            $cart->getData()->set('product-' . $productId, $product);

            $behavior = [
                ...$clonedContext->getPermissions(),
                CheckoutPermissions::SKIP_PROMOTION => true,
                PromotionDeliveryProcessor::SKIP_DELIVERY_RECALCULATION => true,
                ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION => true,
                CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
            ];

            $shippingMethodIds = [$clonedContext->getShippingMethod()->getId()];
            if ($request->isMethod(Request::METHOD_POST)) {
                // all shipping Methods
                $shippingMethodIds = null;
            }

            $shippingMethods = $this->loadShippingMethods($clonedContext, $shippingMethodIds);

            $deliveries = new DeliveryCostCollection();
            foreach ($shippingMethods as $shippingMethod) {
                // Setting data to avoid loading them twice - and separate
                $cart->getData()->set('shipping-method-' . $shippingMethod->getId(), $shippingMethod);
                $clonedContext->assign(['shippingMethod' => $shippingMethod]);

                $delivery = $this->processor
                    ->process($cart, $clonedContext, new CartBehavior($behavior))
                    ->getDeliveries()->first();

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

    #[Route(
        path: '/store-api/checkout/delivery-cost/cart',
        name: 'store-api.checkout.delivery-cost.cart',
        methods: [Request::METHOD_GET]
    )]
    public function deliveryCostsCart(SalesChannelContext $salesChannelContext): DeliveryCostRouteResponse
    {
        return Profiler::trace('delivery-cost-calculator::cart', function () use ($salesChannelContext) {
            $originalCart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

            $behavior = [
                ...$salesChannelContext->getPermissions(),
                CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
            ];

            $shippingMethods = $this->loadShippingMethods($salesChannelContext);

            $deliveries = new DeliveryCostCollection();
            foreach ($shippingMethods as $shippingMethod) {
                if ($salesChannelContext->getShippingMethod()->getId() === $shippingMethod->getId()) {
                    $delivery = $originalCart->getDeliveries()->first();
                } else {
                    $delivery = $this->resolveCartDelivery(
                        $shippingMethod,
                        $salesChannelContext,
                        $originalCart,
                        $behavior,
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

    private function validateProductId(string $productId, SalesChannelContext $salesChannelContext): ProductEntity
    {
        $validProductId = $this->productGateway->get([$productId], $salesChannelContext)->get($productId);
        if ($validProductId === null) {
            throw CartException::productNotFound($productId);
        }

        return $validProductId;
    }

    private function loadShippingMethods(SalesChannelContext $context, ?array $shippingMethodIds = null): ShippingMethodCollection
    {
        $criteria = (new Criteria($shippingMethodIds))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsAnyFilter('availabilityRuleId', [null, ...$context->getRuleIds()]))
            ->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()))
            ->addAssociations(['deliveryTime', 'tax'])
            ->setTitle('cart::shipping-methods');

        $criteria->getAssociation('prices')
            ->addFilter(new EqualsAnyFilter('ruleId', [null, ...$context->getRuleIds()]));

        /** @var ShippingMethodCollection $result */
        $result = $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities();

        return $result;
    }

    /**
     * @param array<string, bool> $behavior
     */
    private function resolveCartDelivery(
        ShippingMethodEntity $shippingMethod,
        SalesChannelContext $salesChannelContext,
        Cart $originalCart,
        array $behavior,
    ): ?Delivery {
        $clonedContext = clone $salesChannelContext;
        $cart = clone $originalCart;

        // Setting data to avoid loading them twice - and separate
        $cart->getData()->set('shipping-method-' . $shippingMethod->getId(), $shippingMethod);
        $clonedContext->assign(['shippingMethod' => $shippingMethod]);

        $calculatedCart = $this->cartRuleLoader->loadByCart($clonedContext, $cart, new CartBehavior($behavior))
            ->getCart();

        $availableShippingMethods = $this->checkoutGatewayRoute
            ->load(new Request(), $calculatedCart, $clonedContext)
            ->getShippingMethods();

        if (!$availableShippingMethods->has($shippingMethod->getId())) {
            return null;
        }

        return $calculatedCart->getDeliveries()->first();
    }
}
