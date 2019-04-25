<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NavigationPageController extends StorefrontController
{
    /**
     * @var NavigationPageLoader|PageLoaderInterface
     */
    private $navigationPageLoader;

    public function __construct(PageLoaderInterface $navigationPageLoader)
    {
        $this->navigationPageLoader = $navigationPageLoader;
    }

    /**
     * @Route("/navigation/{navigationId}", name="frontend.navigation.page", options={"seo"=true}, methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/content/index.html.twig', ['page' => $page]);
    }
}
