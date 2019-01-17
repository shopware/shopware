<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Listing\ListingPageLoader;
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
     * @Route("/listing/{categoryId}", name="frontend.listing.page", options={"seo"=true}, methods={"GET"})
     */
    public function index(CheckoutContext $context, InternalRequest $request): Response
    {
        $page = $this->listingPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/listing/index.html.twig', [
                'page' => $page,
            ]
        );
    }
}
