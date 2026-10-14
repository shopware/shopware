<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\Exception\StorefrontException;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\System\SalesChannel\SalesChannelAnalyticsLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function __construct(
        private readonly AbstractBreadcrumbRoute $breadcrumbRoute,
        private readonly SalesChannelAnalyticsLoader $analyticsLoader,
    ) {
    }

    /**
     * The category path analytics report for a product, as the category names from the top level
     * down. Product elements do not carry it, because loading the categories of every product would
     * slow down each render for an event that only fires on a click, so the analytics script asks
     * for it when it is needed.
     *
     * It is the breadcrumb of the product detail page, resolved by the Store API breadcrumb route.
     * Only the analytics script calls it, so a sales channel without active analytics gets a 404
     * before anything is loaded.
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
        if (!$this->analyticsLoader->isActive($context)) {
            throw StorefrontException::analyticsNotActive();
        }

        $productId = $request->query->getString('productId');

        if (!Uuid::isValid($productId)) {
            throw RoutingException::invalidRequestParameter('productId');
        }

        // the Store API route reads the product from the `id` attribute and the kind from `type`
        $breadcrumbRequest = $request->duplicate(['type' => 'product']);
        $breadcrumbRequest->attributes->set('id', $productId);

        try {
            $breadcrumbs = $this->breadcrumbRoute
                ->load($breadcrumbRequest, $context)
                ->getBreadcrumbCollection();
        } catch (BreadcrumbException $exception) {
            // a product without a category in the sales channel has no breadcrumb, which is a valid answer
            if ($exception->getErrorCode() === BreadcrumbException::BREADCRUMB_CATEGORY_NOT_FOUND) {
                return $this->json([]);
            }

            throw $exception;
        }

        return $this->json(array_values($breadcrumbs->map(static fn (Breadcrumb $breadcrumb) => $breadcrumb->name)));
    }
}
