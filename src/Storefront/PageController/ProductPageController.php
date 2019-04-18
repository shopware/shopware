<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductPageController extends StorefrontController
{
    /**
     * @var ProductPageLoader|PageLoaderInterface
     */
    private $detailPageLoader;

    public function __construct(PageLoaderInterface $detailPageLoader)
    {
        $this->detailPageLoader = $detailPageLoader;
    }

    /**
     * @Route("/detail/{productId}", name="frontend.detail.page", options={"seo"="true"}, methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->detailPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/product-detail/index.html.twig', ['page' => $page]);
    }
}
