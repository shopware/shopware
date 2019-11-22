<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
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

    public function __construct(SearchPageLoader $searchPageLoader, SuggestPageLoader $suggestPageLoader)
    {
        $this->searchPageLoader = $searchPageLoader;
        $this->suggestPageLoader = $suggestPageLoader;
    }

    /**
     * @HttpCache()
     * @Route("/search", name="frontend.search.page", methods={"GET"})
     */
    public function search(SalesChannelContext $context, Request $request): Response
    {
        try {
            $page = $this->searchPageLoader->load($request, $context);
        } catch (MissingRequestParameterException $missingRequestParameterException) {
            return $this->forwardToRoute('frontend.home.page');
        }

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    /**
     * @HttpCache()
     * @Route("/suggest", name="frontend.search.suggest", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->suggestPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/layout/header/search-suggest.html.twig', ['page' => $page]);
    }
}
