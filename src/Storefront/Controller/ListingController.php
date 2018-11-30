<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Page\Listing\ListingPageLoader;
use Shopware\Storefront\Page\Listing\ListingPageRequest;
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
     */
    public function index(string $id, CheckoutContext $context, ListingPageRequest $request): Response
    {
        $request->setNavigationId($id);

        $listingPage = $this->listingPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/listing/index.html.twig', [
            'listing' => $listingPage,
        ]);
    }
}
