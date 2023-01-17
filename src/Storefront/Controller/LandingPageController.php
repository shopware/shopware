<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedHook;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @package content
 *
 * @internal
 */
class LandingPageController extends StorefrontController
{
    /**
     * @var LandingPageLoader
     */
    private $landingPageLoader;

    /**
     * @internal
     */
    public function __construct(
        LandingPageLoader $landingPageLoader
    ) {
        $this->landingPageLoader = $landingPageLoader;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/landingPage/{landingPageId}", name="frontend.landing.page", methods={"GET"}, defaults={"_httpCache"=true})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->landingPageLoader->load($request, $context);

        $this->hook(new LandingPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }
}
