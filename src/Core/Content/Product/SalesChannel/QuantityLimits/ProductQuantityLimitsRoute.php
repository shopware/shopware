<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\QuantityLimits;

use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @codeCoverageIgnore Simple DTO with no logic
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('inventory')]
class ProductQuantityLimitsRoute extends AbstractProductQuantityLimitsRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<SalesChannelProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator,
    ) {
    }

    public function getDecorated(): AbstractProductQuantityLimitsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/{productId}/quantity-limits',
        name: 'store-api.product.quantity-limits',
        methods: [Request::METHOD_GET],
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context): ProductQuantityLimitsRouteResponse
    {
        $criteria = new Criteria([$productId]);
        $criteria->setTitle('product-quantity-limits-route');

        $criteria->addFields([
            'minPurchase',
            'maxPurchase',
            'purchaseSteps',
            'isCloseout',
            'stock',
        ]);

        $product = $this->productRepository->search($criteria, $context)->first();

        if ($product === null) {
            throw ProductException::productNotFound($productId);
        }

        $maxPurchase = $this->maxPurchaseCalculator->calculate($product, $context);
        $minPurchase = $product->get('minPurchase') ?? 1;
        $purchaseSteps = $product->get('purchaseSteps') ?? 1;

        return new ProductQuantityLimitsRouteResponse(
            new ProductQuantityLimitsResult(
                $product->getId(),
                $minPurchase,
                $purchaseSteps,
                $maxPurchase,
            )
        );
    }
}
