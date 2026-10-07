<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AnalyticsController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractBreadcrumbRoute $breadcrumbRoute)
    {
    }

    /**
     * The category path analytics reports for a product, as the list of category names from the top
     * level down. A product box on a listing, a slider or a Shopping Experience page does not carry it,
     * because loading every category of every product would slow down each render for an event that
     * only fires on a click, so the storefront asks for it when it is needed.
     *
     * It is the breadcrumb of the product detail page, resolved by the Store API breadcrumb route.
     * Only the analytics script calls it, which is only loaded for a sales channel with analytics, so
     * a sales channel without analytics gets a 404.
     */
    #[Route(
        path: '/widgets/analytics/product-categories',
        name: 'frontend.analytics.product-categories',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET]
    )]
    public function productCategories(Request $request, SalesChannelContext $context): JsonResponse
    {
        if ($context->getSalesChannel()->getAnalyticsId() === null) {
            return $this->json([], Response::HTTP_NOT_FOUND);
        }

        $productId = $request->query->getString('productId');

        if (!Uuid::isValid($productId)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        // the Store API route reads the product from the `id` attribute and the kind from `type`
        $breadcrumbRequest = $request->duplicate(['type' => 'product']);
        $breadcrumbRequest->attributes->set('id', $productId);

        try {
            $breadcrumb = $this->breadcrumbRoute
                ->load($breadcrumbRequest, $context)
                ->getBreadcrumbCollection();
        } catch (BreadcrumbException $exception) {
            // a product without a category available in the sales channel has no breadcrumb, which
            // is a valid answer; anything else is a real error
            if ($exception->getErrorCode() !== BreadcrumbException::BREADCRUMB_CATEGORY_NOT_FOUND) {
                throw $exception;
            }

            return $this->json([]);
        }

        return $this->json(array_values(array_map(
            static fn ($item) => $item->name,
            $breadcrumb->getElements()
        )));
    }
}
