<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Suggest;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('system-settings')]
class ProductSuggestRoute extends AbstractProductSuggestRoute
{
    public const STATE = 'suggest-route-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly ProductListingLoader $productListingLoader
    ) {
    }

    public function getDecorated(): AbstractProductSuggestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/search-suggest', name: 'store-api.search.suggest', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSuggestRouteResponse
    {
        $result = $this->productListingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        return new ProductSuggestRouteResponse($result);
    }
}
