<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Controller\Widget;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Storefront\StorefrontProductRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Listing\Page\ListingPageRequest;
use Shopware\Storefront\Listing\PageLoader\ListingPageLoader;
use Shopware\Storefront\Search\Page\SearchPageRequest;
use Shopware\Storefront\Search\PageLoader\SearchPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ListingController extends StorefrontController
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
     */
    public function listAction(string $categoryId, ListingPageRequest $request, CheckoutContext $context): JsonResponse
    {
        $request->setNavigationId($categoryId);

        $page = $this->listingPageLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/frontend/listing/listing_ajax.html.twig', [
            'listing' => $page,
        ]);

        $pagination = $this->renderStorefront('@Storefront/frontend/listing/actions/action-pagination.html.twig', [
            'listing' => $page,
        ]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getProducts()->getTotal(),
        ]);
    }

    /**
     * @Route("/widgets/listing/search", name="widgets_listing_search", methods={"GET"})
     */
    public function searchAction(SearchPageRequest $request, CheckoutContext $context): JsonResponse
    {
        $page = $this->searchPageLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/frontend/listing/listing_ajax.html.twig', [
            'listing' => $page,
        ]);

        $pagination = $this->renderStorefront('@Storefront/frontend/listing/actions/action-pagination.html.twig', [
            'listing' => $page,
        ]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getProducts()->getTotal(),
        ]);
    }
}
