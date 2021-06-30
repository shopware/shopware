<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class SearchController extends StorefrontController
{
    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    /**
     * @var SuggestPageLoader
     */
    private $suggestPageLoader;

    /**
     * @var AbstractProductSearchRoute
     */
    private $productSearchRoute;

    public function __construct(
        SearchPageLoader $searchPageLoader,
        SuggestPageLoader $suggestPageLoader,
        AbstractProductSearchRoute $productSearchRoute
    ) {
        $this->searchPageLoader = $searchPageLoader;
        $this->suggestPageLoader = $suggestPageLoader;
        $this->productSearchRoute = $productSearchRoute;
    }

    /**
     * @Since("6.0.0.0")
     * @HttpCache()
     * @Route("/search", name="frontend.search.page", methods={"GET"})
     */
    public function search(SalesChannelContext $context, Request $request): Response
    {
        try {
            $page = $this->searchPageLoader->load($request, $context);
            if ($page->getListing()->getTotal() === 1) {
                $product = $page->getListing()->first();
                if ($request->get('search') === $product->getProductNumber()) {
                    $productId = $product->getId();

                    return $this->forwardToRoute('frontend.detail.page', [], ['productId' => $productId]);
                }
            }
        } catch (MissingRequestParameterException $missingRequestParameterException) {
            return $this->forwardToRoute('frontend.home.page');
        }

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @HttpCache()
     * @Route("/suggest", name="frontend.search.suggest", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->suggestPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/layout/header/search-suggest.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.2.0.0")
     * @HttpCache()
     *
     * Route to load the listing filters
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/search", name="widgets.search.pagelet.v2", methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws MissingRequestParameterException
     */
    public function ajax(Request $request, SalesChannelContext $context): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->searchPageLoader->load($request, $context);

        $response = $this->renderStorefront('@Storefront/storefront/page/search/search-pagelet.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * @Since("6.3.3.0")
     * @HttpCache()
     *
     * Route to load the available listing filters
     *
     * @RouteScope(scopes={"storefront"})
     * @Route("/widgets/search/filter", name="widgets.search.filter", methods={"GET", "POST"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws MissingRequestParameterException
     */
    public function filter(Request $request, SalesChannelContext $context): Response
    {
        $term = $request->get('search');
        if (!$term) {
            throw new MissingRequestParameterException('search');
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
}
