<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Checkout\AjaxCart\CheckoutAjaxCartPageletLoader;
use Shopware\Storefront\Pagelet\Checkout\Info\CheckoutInfoPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutPageletController extends StorefrontController
{
    /**
     * @var CheckoutInfoPageletLoader|PageLoaderInterface
     */
    private $infoLoader;

    /**
     * @var CheckoutAjaxCartPageletLoader|PageLoaderInterface
     */
    private $ajaxCartLoader;

    public function __construct(PageLoaderInterface $infoLoader, PageLoaderInterface $ajaxCartLoader)
    {
        $this->infoLoader = $infoLoader;
        $this->ajaxCartLoader = $ajaxCartLoader;
    }

    /**
     * @Route("/widgets/checkout/info", name="widgets.checkout.info", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function infoAction(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->infoLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/base/header/actions/cart-widget.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/ajax-cart", name="frontend.cart.detail", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function ajaxCart(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->ajaxCartLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/base/header/cart-mini.html.twig', ['page' => $page]);
    }
}
