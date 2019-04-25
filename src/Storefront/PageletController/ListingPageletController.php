<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ListingPageletController extends StorefrontController
{
    /**
     * @var ListingPageletLoader|PageLoaderInterface
     */
    private $listingPageletLoader;

    public function __construct(PageLoaderInterface $listingPageletLoader)
    {
        $this->listingPageletLoader = $listingPageletLoader;
    }

    /**
     * @Route("/widgets/listing/list/{categoryId}", name="widgets_listing_list", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function listAction(Request $request, SalesChannelContext $context): JsonResponse
    {
        $request->request->set('no-aggregations', true);
        $request->request->set('product-min-visibility', ProductVisibilityDefinition::VISIBILITY_ALL);

        /** @var StorefrontSearchResult $page */
        $page = $this->listingPageletLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        $pagination = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getTotal(),
        ]);
    }

    /**
     * @Route("/widgets/listing/search", name="widgets_listing_search", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function searchAction(Request $request, SalesChannelContext $context): JsonResponse
    {
        $request->request->set('no-aggregations', true);
        $request->request->set('product-min-visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH);

        /** @var StorefrontSearchResult $page */
        $page = $this->listingPageletLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        $pagination = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getTotal(),
        ]);
    }
}
