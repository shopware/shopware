<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Page\Navigation\NavigationPageLoaderInterface;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class NavigationController extends StorefrontController
{
    private NavigationPageLoaderInterface $navigationPageLoader;

    private MenuOffcanvasPageletLoaderInterface $offcanvasLoader;

    public function __construct(
        NavigationPageLoaderInterface $navigationPageLoader,
        MenuOffcanvasPageletLoaderInterface $offcanvasLoader
    ) {
        $this->navigationPageLoader = $navigationPageLoader;
        $this->offcanvasLoader = $offcanvasLoader;
    }

    /**
     * @Since("6.0.0.0")
     * @HttpCache()
     * @Route("/", name="frontend.home.page", options={"seo"="true"}, methods={"GET"})
     */
    public function home(Request $request, SalesChannelContext $context): ?Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.3.3.0")
     * @HttpCache()
     * @Route("/navigation/{navigationId}", name="frontend.navigation.page", options={"seo"=true}, methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @HttpCache()
     * @Route("/widgets/menu/offcanvas", name="frontend.menu.offcanvas", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasLoader->load($request, $context);

        $response = $this->renderStorefront(
            '@Storefront/storefront/layout/navigation/offcanvas/navigation-pagelet.html.twig',
            ['page' => $page]
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }
}
