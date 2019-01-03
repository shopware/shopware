<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\ProductDetail\ProductDetailPageLoader;
use Shopware\Storefront\Page\ProductDetail\ProductDetailPageRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends StorefrontController
{
    /**
     * @var ProductDetailPageLoader
     */
    private $detailPageLoader;

    public function __construct(ProductDetailPageLoader $detailPageLoader)
    {
        $this->detailPageLoader = $detailPageLoader;
    }

    /**
     * @Route("/detail/{id}", name="frontend.detail.page", options={"seo"="true"}, methods={"GET"})
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     * @throws \Twig_Error_Loader
     */
    public function index(string $id, CheckoutContext $context, ProductDetailPageRequest $request): Response
    {
        $page = $this->detailPageLoader->load($id, $request, $context);

        $xhr = $request->isXmlHttpRequest();
        $template = '@Storefront/frontend/detail/index.html.twig';

        if ($xhr) {
            $template = '@Storefront/frontend/detail/content.html.twig';
        }

        return $this->renderStorefront($template, $page->toArray());
    }
}
