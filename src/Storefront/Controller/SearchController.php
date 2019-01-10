<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends StorefrontController
{
    /**
     * @var \Shopware\Storefront\Page\Search\SearchPageLoader
     */
    private $searchPageLoader;

    public function __construct(SearchPageLoader $searchPageLoader)
    {
        $this->searchPageLoader = $searchPageLoader;
    }

    /**
     * @Route("/search", name="frontend.search.page", options={"seo"=false}, methods={"GET"})
     *
     * @throws \Twig_Error_Loader
     *
     * @return Response
     */
    public function index(CheckoutContext $context, SearchPageRequest $searchPageRequest): Response
    {
        $page = $this->searchPageLoader->load($searchPageRequest, $context);

        return $this->renderStorefront(
            '@Storefront/frontend/search/index.html.twig', [
                'page' => $page,
            ]
        );
    }

    /**
     * @Route("/search/suggest", name="frontend.search.suggest", methods={"GET"})
     *
     * @param CheckoutContext   $context
     * @param SearchPageRequest $searchPageRequest
     *
     * @throws \Twig_Error_Loader
     *
     * @return Response
     */
    public function suggest(CheckoutContext $context, Request $request, SearchPageRequest $searchPageRequest): Response
    {
        $searchTerm = $request->get('search');

        if (empty($searchTerm)) {
            return $this->render('');
        }
        $page = $this->searchPageLoader->load($searchPageRequest, $context);

        return $this->renderStorefront(
            '@Storefront/frontend/search/ajax.html.twig',
            [
                'listing' => [
                    'page' => $page,
                ],
                'searchTerm' => $searchTerm,
            ]
        );
    }
}
