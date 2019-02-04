<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NavigationPageController extends StorefrontController
{
    /**
     * @var NavigationPageLoader
     */
    private $navigationPageLoader;

    public function __construct(NavigationPageLoader $navigationPageLoader)
    {
        $this->navigationPageLoader = $navigationPageLoader;
    }

    /**
     * @Route("/navigation/{navigationId}", name="frontend.navigation.page", options={"seo"=true}, methods={"GET"})
     *
     * @param CheckoutContext $context
     * @param InternalRequest $request
     *
     * @return Response
     */
    public function index(CheckoutContext $context, InternalRequest $request): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/index/index.html.twig', ['page' => $page]);
    }
}
