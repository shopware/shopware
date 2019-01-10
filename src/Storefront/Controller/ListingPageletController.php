<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Listing\ListingPageLoader;
use Shopware\Storefront\Page\Listing\ListingPageRequest;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ListingPageletController extends StorefrontController
{
    /**
     * @var StorefrontProductRepository
     */
    private $repository;

    /**
     * @var ListingPageLoader
     */
    private $listingPageLoader;

    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    public function __construct(
        StorefrontProductRepository $repository,
        ListingPageLoader $listingPageLoader,
        SearchPageLoader $searchPageLoader
    ) {
        $this->repository = $repository;
        $this->listingPageLoader = $listingPageLoader;
        $this->searchPageLoader = $searchPageLoader;
    }

    /**
     * @Route("/widgets/listing/top_seller", name="widgets_top_seller", methods={"GET"})
     *
     * @throws \Twig_Error_Loader
     */
    public function topSellerAction(CheckoutContext $context): Response
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $products = $this->repository->search($criteria, $context);

        return $this->renderStorefront('@Storefront/widgets/listing/top_seller.html.twig', [
            'products' => $products,
        ]);
    }

    /**
     * @Route("/widgets/listing/list/{categoryId}", name="widgets_listing_list", methods={"GET"})
     *
     * @throws \Twig_Error_Loader
     */
    public function listAction(string $categoryId, ListingPageRequest $request, CheckoutContext $context): JsonResponse
    {
        $request->getListingRequest()->setNavigationId($categoryId);

        $page = $this->listingPageLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/frontend/listing/listing_ajax.html.twig', [
                'page' => $page,
            ]
        );

        $pagination = $this->renderStorefront('@Storefront/frontend/listing/actions/action-pagination.html.twig', [
                'page' => $page,
            ]
        );

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getListing()->getProducts()->getTotal(),
        ]);
    }

    /**
     * @Route("/widgets/listing/search", name="widgets_listing_search", methods={"GET"})
     *
     * @throws \Twig_Error_Loader
     */
    public function searchAction(SearchPageRequest $request, CheckoutContext $context): JsonResponse
    {
        $page = $this->searchPageLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/frontend/listing/listing_ajax.html.twig', [
                'page' => $page,
            ]
        );

        $pagination = $this->renderStorefront('@Storefront/frontend/listing/actions/action-pagination.html.twig', [
                'page' => $page,
            ]
        );

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getListing()->getProducts()->getTotal(),
        ]);
    }
}
