<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingCost;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryProcessor;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
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
class ProductShippingCostRoute extends AbstractProductShippingCostRoute
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
    ) {
    }

    public function getDecorated(): AbstractProductShippingCostRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Calculates shipping costs for the requested product and matching shipping methods.
     *
     * This route can be expensive because each shipping method requires an isolated cart calculation.
     * Only call it when shipping costs are actually needed and prefer adding a cache layer for repeated requests.
     */
    #[Route(
        path: '/store-api/shipping-cost/product/{productId}',
        name: 'store-api.shipping-cost.product',
        requirements: ['productId' => Uuid::VALID_PATTERN],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ShippingMethodDefinition::ENTITY_NAME],
        methods: [Request::METHOD_GET]
    )]
    public function shippingCostsByProduct(string $productId, Criteria $criteria, SalesChannelContext $salesChannelContext): ShippingCostRouteResponse
    {
        return Profiler::trace('shipping-cost-calculator::product', function () use ($productId, $criteria, $salesChannelContext) {
            $clonedContext = clone $salesChannelContext;
            $product = $this->loadProduct($productId, $clonedContext);

            $cart = (new Cart(Uuid::randomHex()))
                ->add(new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));
            $cart->getData()->set('product-' . $productId, $product);

            $behavior = [
                CheckoutPermissions::SKIP_PROMOTION => true,
                PromotionDeliveryProcessor::SKIP_DELIVERY_RECALCULATION => true,
                CheckoutPermissions::SKIP_PRODUCT_STOCK_VALIDATION => true,
                ...$clonedContext->getPermissions(),
                CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
            ];

            $shippingMethods = $this->loadShippingMethods($criteria, $clonedContext);

            $shippingCosts = new ShippingCostCollection();
            foreach ($shippingMethods as $shippingMethod) {
                // Setting data to avoid loading them twice - and each separate
                $cart->getData()->set('shipping-method-' . $shippingMethod->getId(), $shippingMethod);
                $clonedContext->assign(['shippingMethod' => $shippingMethod]);

                $deliveries = $this->processor
                    ->process($cart, $clonedContext, new CartBehavior($behavior))
                    ->getDeliveries();

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

    private function loadProduct(string $productId, SalesChannelContext $salesChannelContext): ProductEntity
    {
        $product = $this->productGateway->get([$productId], $salesChannelContext)->get($productId);
        if ($product === null) {
            throw CartException::productNotFound($productId);
        }

        return $product;
    }

    private function loadShippingMethods(Criteria $criteria, SalesChannelContext $context): ShippingMethodCollection
    {
        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsAnyFilter('availabilityRuleId', [null, ...$context->getRuleIds()]))
            ->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()))
            ->addAssociations(['deliveryTime', 'tax'])
            ->setTitle('cart::shipping-methods');

        $criteria->getAssociation('prices')
            ->addFilter(new EqualsAnyFilter('ruleId', [null, ...$context->getRuleIds()]));

        return $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities();
    }
}
