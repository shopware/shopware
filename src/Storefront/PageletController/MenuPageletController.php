<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MenuPageletController extends StorefrontController
{
    /**
     * @var MenuOffcanvasPageletLoader|PageLoaderInterface
     */
    private $offcanvasLoader;

    public function __construct(PageLoaderInterface $offcanvasLoader)
    {
        $this->offcanvasLoader = $offcanvasLoader;
    }

    /**
     * @Route("/widgets/menu/offcanvas", name="widgets.menu.offcanvas", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function menuOffcanvasAction(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/base/navigation/offcanvas/navigation.html.twig', ['page' => $page]);
    }
}
