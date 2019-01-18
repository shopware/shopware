<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Search\SearchPageLoader;
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
    public function index(CheckoutContext $context, InternalRequest $searchPageRequest): Response
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
     * @param CheckoutContext $context
     * @param InternalRequest $searchPageRequest
     *
     * @return Response
     */
    public function suggest(CheckoutContext $context, Request $request, InternalRequest $searchPageRequest): Response
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
