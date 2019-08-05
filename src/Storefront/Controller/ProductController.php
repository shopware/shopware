<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends StorefrontController
{
    /**
     * @var ProductPageLoader
     */
    private $productPageLoader;

    /**
     * @var ProductCombinationFinder
     */
    private $combinationFinder;
    /**
     * @var MinimalQuickViewPageLoader
     */
    private $minimalQuickViewPageLoader;

    public function __construct(
        ProductPageLoader $productPageLoader,
        ProductCombinationFinder $combinationFinder,
        MinimalQuickViewPageLoader $minimalQuickViewPageLoader
    ) {
        $this->productPageLoader = $productPageLoader;
        $this->combinationFinder = $combinationFinder;
        $this->minimalQuickViewPageLoader = $minimalQuickViewPageLoader;
    }

    /**
     * @Route("/detail/{productId}", name="frontend.detail.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->productPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/product-detail/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/detail/{productId}/switch", name="frontend.detail.switch", methods={"POST"})
     */
    public function switch(string $productId, RequestDataBag $data, SalesChannelContext $context): RedirectResponse
    {
        $switchedOption = $data->get('switched');
        $newOptions = json_decode($data->get('options'), true);

        $redirect = $this->combinationFinder->find(
            $productId,
            $switchedOption,
            $newOptions,
            $context
        );

        return $this->redirectToRoute('frontend.detail.page', ['productId' => $redirect->getVariantId()]);
    }

    /**
     * @Route("/quickview/{productId}", name="widgets.quickview.minimal", methods={"GET"}, defaults={"XmlHttpRequest": true})
     */
    public function quickviewMinimal(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->minimalQuickViewPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/component/product/quickview/minimal.html.twig', ['page' => $page]);
    }
}
