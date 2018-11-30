<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends StorefrontController
{
    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    public function __construct(SearchPageLoader $searchPageLoader)
    {
        $this->searchPageLoader = $searchPageLoader;
    }

    /**
     * @Route("/search", name="frontend.search.page", options={"seo"=false}, methods={"GET"})
     *
     * @return Response
     */
    public function index(CheckoutContext $context, SearchPageRequest $searchPageRequest): Response
    {
        $listing = $this->searchPageLoader->load($searchPageRequest, $context);

        return $this->renderStorefront(
            '@Storefront/frontend/search/index.html.twig',
            [
                'listing' => $listing,
                'productBoxLayout' => $listing->getProductBoxLayout(),
                'searchTerm' => $searchPageRequest->getSearchTerm(),
            ]
        );
    }

    /**
     * @Route("/search/suggest", name="frontend.search.suggest", methods={"GET"})
     *
     * @param CheckoutContext   $context
     * @param SearchPageRequest $searchPageRequest
     *
     * @return Response
     */
    public function suggest(CheckoutContext $context, Request $request, SearchPageRequest $searchPageRequest): Response
    {
        $searchTerm = $request->get('search');

        if (empty($searchTerm)) {
            return $this->render('');
        }

        return $this->renderStorefront(
            '@Storefront/frontend/search/ajax.html.twig',
            [
                'listing' => $this->searchPageLoader->load($searchPageRequest, $context),
                'searchTerm' => $searchTerm,
            ]
        );
    }
}
