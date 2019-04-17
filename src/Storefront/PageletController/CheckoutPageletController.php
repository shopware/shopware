<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Controller\XmlHttpRequestableInterface;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Checkout\AjaxCart\CheckoutAjaxCartPageletLoader;
use Shopware\Storefront\Pagelet\Checkout\Info\CheckoutInfoPageletLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutPageletController extends StorefrontController implements XmlHttpRequestableInterface
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
     * @Route("/widgets/checkout/info", name="widgets.checkout.info", methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function infoAction(InternalRequest $request, SalesChannelContext $context): Response
    {
        $page = $this->infoLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/base/header/actions/cart-widget.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/ajax-cart", name="frontend.cart.detail", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function ajaxCart(InternalRequest $request, SalesChannelContext $context): Response
    {
        $page = $this->ajaxCartLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/base/header/cart-mini.html.twig', ['page' => $page]);
    }
}
