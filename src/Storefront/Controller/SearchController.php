<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Pagelet\Suggest\SuggestPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends StorefrontController
{
    /**
     * @var SearchPageLoader|PageLoaderInterface
     */
    private $searchPageLoader;

    /**
     * @var SuggestPageletLoader|PageLoaderInterface
     */
    private $suggestPageletLoader;

    public function __construct(PageLoaderInterface $searchPageLoader, PageLoaderInterface $suggestPageletLoader)
    {
        $this->searchPageLoader = $searchPageLoader;
        $this->suggestPageletLoader = $suggestPageletLoader;
    }

    /**
     * @Route("/search", name="frontend.search.page", options={"seo"=false}, methods={"GET"})
     */
    public function search(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->searchPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/search/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/suggest", name="frontend.search.suggest", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->suggestPageletLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/layout/header/search-suggest.html.twig', ['page' => $page]);
    }
}
