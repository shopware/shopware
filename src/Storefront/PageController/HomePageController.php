<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Home\HomePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomePageController extends StorefrontController
{
    /**
     * @var HomePageLoader|PageLoaderInterface
     */
    private $homePageLoader;

    public function __construct(PageLoaderInterface $homePageLoader)
    {
        $this->homePageLoader = $homePageLoader;
    }

    /**
     * @Route("/", name="frontend.home.page", methods={"GET"})
     */
    public function index(Request $request, SalesChannelContext $context): ?Response
    {
        $data = $this->homePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/home/index.html.twig', ['page' => $data]);
    }
}
