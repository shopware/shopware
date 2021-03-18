<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class LandingPageController extends StorefrontController
{
    /**
     * @var LandingPageLoader
     */
    private $landingPageLoader;

    public function __construct(
        LandingPageLoader $landingPageLoader
    ) {
        $this->landingPageLoader = $landingPageLoader;
    }

    /**
     * @Since("6.4.0.0")
     * @HttpCache()
     * @Route("/landingPage/{landingPageId}", name="frontend.landing.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->landingPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }
}
