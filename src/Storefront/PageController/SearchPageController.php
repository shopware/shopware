<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Pagelet\Listing\Subscriber\SearchTermSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchPageController extends StorefrontController
{
    /**
     * @var SearchPageLoader|PageLoaderInterface
     */
    private $searchPageLoader;

    public function __construct(PageLoaderInterface $searchPageLoader)
    {
        $this->searchPageLoader = $searchPageLoader;
    }

    /**
     * @Route("/search", name="frontend.search.page", options={"seo"=false}, methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        if (!$request->query->has(SearchTermSubscriber::TERM_PARAMETER)) {
            throw new MissingRequestParameterException(SearchTermSubscriber::TERM_PARAMETER);
        }

        $page = $this->searchPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/search/index.html.twig', ['page' => $page]);
    }
}
