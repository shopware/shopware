<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedHook;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('content')]
class LandingPageController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly LandingPageLoader $landingPageLoader)
    {
    }

    #[Route(path: '/landingPage/{landingPageId}', name: 'frontend.landing.page', defaults: ['_httpCache' => true], methods: ['GET'])]
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->landingPageLoader->load($request, $context);

        $this->hook(new LandingPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }
}
