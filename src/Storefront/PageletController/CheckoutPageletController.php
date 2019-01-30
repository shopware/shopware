<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Pagelet\Checkout\AjaxCart\CheckoutAjaxCartPageletLoader;
use Shopware\Storefront\Pagelet\Checkout\Info\CheckoutInfoPageletLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutPageletController extends StorefrontController
{
    /**
     * @var CheckoutInfoPageletLoader
     */
    private $infoLoader;

    /**
     * @var CheckoutAjaxCartPageletLoader
     */
    private $ajaxCartLoader;

    public function __construct(CheckoutInfoPageletLoader $infoLoader, CheckoutAjaxCartPageletLoader $ajaxCartLoader)
    {
        $this->infoLoader = $infoLoader;
        $this->ajaxCartLoader = $ajaxCartLoader;
    }

    /**
     * @Route("/widgets/checkout/info", name="widgets/checkout/info", methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function infoAction(InternalRequest $request, CheckoutContext $context): Response
    {
        $page = $this->infoLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/ajax-cart", name="frontend.cart.detail", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function ajaxCart(InternalRequest $request, CheckoutContext $context): Response
    {
        $page = $this->ajaxCartLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);
    }
}
