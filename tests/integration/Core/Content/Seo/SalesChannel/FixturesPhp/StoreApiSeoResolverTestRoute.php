<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo\SalesChannel\FixturesPhp;

use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class StoreApiSeoResolverTestRoute
{
    public function __construct(
        private readonly AbstractCategoryRoute $categoryRoute,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
    ) {
    }

    #[
        Route(
            path: '/store-api/test/store-api-seo-resolver/no-auth-required',
            name: 'store-api.test.store_api_seo_resolver.no_auth_required',
            defaults: ['auth_required' => false],
            methods: [Request::METHOD_GET]
        )
    ]
    public function noAuthRequiredAction(Request $request): StoreApiResponse
    {
        $salesChannelId = $request->get('sales-channel-id');

        return $this->categoryRoute->load(
            CategoryRoute::HOME,
            $request,
            $this->contextFactory->create(Uuid::randomHex(), $salesChannelId)
        );
    }
}
