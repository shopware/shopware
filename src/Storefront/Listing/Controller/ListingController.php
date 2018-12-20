<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Listing\Page\ListingPageRequest;
use Shopware\Storefront\Listing\PageLoader\ListingPageLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ListingController extends StorefrontController
{
    /**
     * @var ListingPageLoader
     */
    private $listingPageLoader;

    public function __construct(ListingPageLoader $listingPageLoader)
    {
        $this->listingPageLoader = $listingPageLoader;
    }

    /**
     * @Route("/listing/{id}", name="frontend.listing.page", options={"seo"=true}, methods={"GET"})
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     */
    public function index(string $id, CheckoutContext $context, ListingPageRequest $request): Response
    {
        $request->setNavigationId($id);

        $page = $this->listingPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/listing/index.html.twig', $page->toArray());
    }
}
