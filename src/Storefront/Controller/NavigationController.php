<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NavigationController extends StorefrontController
{
    /**
     * @var NavigationPageLoader
     */
    private $navigationPageLoader;

    /**
     * @var MenuOffcanvasPageletLoader
     */
    private $offcanvasLoader;

    public function __construct(NavigationPageLoader $navigationPageLoader, MenuOffcanvasPageletLoader $offcanvasLoader)
    {
        $this->navigationPageLoader = $navigationPageLoader;
        $this->offcanvasLoader = $offcanvasLoader;
    }

    /**
     * @Route("/", name="frontend.home.page", methods={"GET"})
     */
    public function home(Request $request, SalesChannelContext $context): ?Response
    {
        $data = $this->navigationPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/content/index.html.twig', ['page' => $data]);
    }

    /**
     * @Route("/navigation/{navigationId}", name="frontend.navigation.page", options={"seo"=true}, methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        try {
            $page = $this->navigationPageLoader->load($request, $context);
        } catch (CategoryNotFoundException $exception) {
            return $this->redirectToRoute('frontend.home.page');
        }

        return $this->renderStorefront('@Storefront/page/content/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/widgets/menu/offcanvas", name="frontend.menu.offcanvas", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/layout/navigation/offcanvas/navigation.html.twig', ['page' => $page]);
    }
}
