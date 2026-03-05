<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\QuantityLimits;

use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly SystemConfigService $config,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
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

        $this->addFilters($context, $criteria);

        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

        if (!($product instanceof SalesChannelProductEntity)) {
            throw ProductException::productNotFound($productId);
        }

        $maxPurchase = $this->maxPurchaseCalculator->calculate($product, $context);
        $minPurchase = $product->getMinPurchase() ?? 1;
        $purchaseSteps = $product->getPurchaseSteps() ?? 1;

        return new ProductQuantityLimitsRouteResponse(
            new ProductQuantityLimitsResult(
                $product->getId(),
                $minPurchase,
                $purchaseSteps,
                $maxPurchase,
            )
        );
    }

    private function addFilters(SalesChannelContext $context, Criteria $criteria): void
    {
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_LINK)
        );

        $salesChannelId = $context->getSalesChannelId();

        $hideCloseoutProductsWhenOutOfStock = $this->config->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if ($hideCloseoutProductsWhenOutOfStock) {
            $filter = $this->productCloseoutFilterFactory->create($context);
            $filter->addQuery(new EqualsFilter('product.parentId', null));
            $criteria->addFilter($filter);
        }
    }
}
