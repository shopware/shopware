<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Search\SearchPage;
use Shopware\Storefront\Page\Search\SearchPageLoadedHook;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Search\SearchWidgetLoadedHook;
use Shopware\Storefront\Page\Suggest\SuggestPageLoadedHook;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('buyers-experience')]
class SearchController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SearchPageLoader $searchPageLoader,
        private readonly SuggestPageLoader $suggestPageLoader,
        private readonly AbstractProductSearchRoute $productSearchRoute
    ) {
    }

    #[Route(path: '/search', name: 'frontend.search.page', defaults: ['_httpCache' => true], methods: ['GET'])]
    public function search(SalesChannelContext $context, Request $request): Response
    {
        try {
            $page = $this->searchPageLoader->load($request, $context);

            $response = $this->handleFirstHit($request, $page);

            if ($response !== null) {
                return $response;
            }
        } catch (RoutingException $e) {
            if ($e->getErrorCode() !== RoutingException::MISSING_REQUEST_PARAMETER_CODE) {
                throw $e;
            }

            return $this->forwardToRoute('frontend.home.page');
        }

        $this->hook(new SearchPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    #[Route(path: '/suggest', name: 'frontend.search.suggest', defaults: ['XmlHttpRequest' => true, '_httpCache' => true], methods: ['GET'])]
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        if (!$request->request->has('no-aggregations')) {
            $request->request->set('no-aggregations', true);
        }

        $page = $this->suggestPageLoader->load($request, $context);

        $this->hook(new SuggestPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/layout/header/search-suggest.html.twig', ['page' => $page]);
    }

    /**
     * Route to load the listing filters
     */
    #[Route(path: '/widgets/search', name: 'widgets.search.pagelet.v2', defaults: ['XmlHttpRequest' => true, '_routeScope' => ['storefront'], '_httpCache' => true], methods: ['GET', 'POST'])]
    public function ajax(Request $request, SalesChannelContext $context): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->searchPageLoader->load($request, $context);

        $this->hook(new SearchWidgetLoadedHook($page, $context));

        $response = $this->renderStorefront('@Storefront/storefront/page/search/search-pagelet.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the available listing filters
     */
    #[Route(path: '/widgets/search/filter', name: 'widgets.search.filter', defaults: ['XmlHttpRequest' => true, '_routeScope' => ['storefront'], '_httpCache' => true], methods: ['GET', 'POST'])]
    public function filter(Request $request, SalesChannelContext $context): Response
    {
        $term = $request->get('search');
        if (!$term) {
            throw RoutingException::missingRequestParameter('search');
        }

        // Allows to fetch only aggregations over the gateway.
        $request->request->set('only-aggregations', true);
        // Allows to convert all post-filters to filters. This leads to the fact that only aggregation values are returned, which are combinable with the previous applied filters.
        $request->request->set('reduce-aggregations', true);
        $criteria = new Criteria();
        $criteria->setTitle('search-page');

        $result = $this->productSearchRoute
            ->load($request, $context, $criteria)
            ->getListingResult();
        $mapped = [];

        foreach ($result->getAggregations() as $aggregation) {
            $mapped[$aggregation->getName()] = $aggregation;
        }

        $response = new JsonResponse($mapped);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    private function handleFirstHit(Request $request, SearchPage $page): ?Response
    {
        if ($page->getListing()->getTotal() > 1) {
            return null;
        }

        $product = $page->getListing()->first();
        if (!$product instanceof ProductEntity) {
            return null;
        }

        if ($request->get('search') === mb_strtolower($product->getProductNumber())) {
            return $this->redirectToRoute('frontend.detail.page', ['productId' => $product->getId()]);
        }

        return null;
    }
}
