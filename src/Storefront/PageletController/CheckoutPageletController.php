<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Account\AddressList\AccountAddressListPageletLoader;
use Shopware\Storefront\Pagelet\Checkout\AjaxCart\CheckoutAjaxCartPageletLoader;
use Shopware\Storefront\Pagelet\Checkout\Info\CheckoutInfoPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    /**
     * @var AccountAddressListPageletLoader|PageLoaderInterface
     */
    private $accountAddresslistLoader;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        PageLoaderInterface $infoLoader,
        PageLoaderInterface $ajaxCartLoader,
        PageLoaderInterface $accountAddresslistLoader,
        TranslatorInterface $translator
    ) {
        $this->infoLoader = $infoLoader;
        $this->ajaxCartLoader = $ajaxCartLoader;
        $this->accountAddresslistLoader = $accountAddresslistLoader;
        $this->translator = $translator;
    }

    /**
     * @Route("/widgets/checkout/info", name="widgets.checkout.info", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function infoAction(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->infoLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/layout/header/actions/cart-widget.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/ajax-cart", name="frontend.cart.detail", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     *
     * @throws CartTokenNotFoundException
     */
    public function ajaxCart(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->ajaxCartLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/component/checkout/offcanvas-cart.html.twig', ['page' => $page]);
    }

    /**
     * @Route(path="/widgets/checkout/addresses", name="widgets.checkout.ajax-addresses", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function ajaxAddresses(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->accountAddresslistLoader->load($request, $context);
        $this->addFlash('success', $this->translator->trans('account.addressDefaultChanged'));

        return $this->renderStorefront('@Storefront/component/checkout/confirm/ajax-addresses.html.twig', ['page' => $page]);
    }
}
