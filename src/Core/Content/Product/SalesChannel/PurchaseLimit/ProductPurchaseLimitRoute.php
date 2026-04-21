<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\PurchaseLimit;

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

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('inventory')]
class ProductPurchaseLimitRoute extends AbstractProductPurchaseLimitRoute
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

    public function getDecorated(): AbstractProductPurchaseLimitRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/product/purchase-limit',
        name: 'store-api.product.purchase-limit',
        methods: [Request::METHOD_GET],
        priority: 1, // keeping priority higher than in \Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute
    )]
    public function readProductsPurchaseLimit(Request $request, SalesChannelContext $context): ProductPurchaseLimitRouteResponse
    {
        /** @var array<string> $ids */
        $ids = $request->query->all('ids');

        if (empty($ids)) {
            throw ProductException::missingRequestParameter('ids');
        }

        $criteria = new Criteria($ids);
        $criteria->setTitle('product-purchase-limit-route');

        $criteria->addFields([
            'minPurchase',
            'maxPurchase',
            'purchaseSteps',
            'isCloseout',
            'stock',
        ]);

        $products = $this->productRepository->search($criteria, $context);

        $results = new ProductPurchaseLimitCollection();

        foreach ($products as $product) {
            $maxPurchase = $this->maxPurchaseCalculator->calculate($product, $context);
            $minPurchase = $product->get('minPurchase') ?? 1;
            $purchaseSteps = $product->get('purchaseSteps') ?? 1;
            $stock = $product->get('stock') ?? null;

            $results->add(new ProductPurchaseLimit(
                $product->getId(),
                $minPurchase,
                $purchaseSteps,
                $maxPurchase,
                $stock,
            ));
        }

        return new ProductPurchaseLimitRouteResponse($results);
    }
}
