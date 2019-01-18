<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Exception\InvalidParameterException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\Checkout\Config\CheckoutConfigPageLoader;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutPageController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CheckoutConfigPageLoader
     */
    private $configPageLoader;

    /**
     * @var CheckoutCartPageLoader
     */
    private $cartPageLoader;

    /**
     * @var CheckoutConfirmPageLoader
     */
    private $confirmPageLoader;

    /**
     * @var CheckoutFinishPageLoader
     */
    private $finishPageLoader;

    public function __construct(
        CartService $cartService,
        CheckoutCartPageLoader $cartPageLoader,
        CheckoutConfirmPageLoader $confirmPageLoader,
        CheckoutFinishPageLoader $finishPageLoader,
        CheckoutConfigPageLoader $configPageLoader
    ) {
        $this->cartService = $cartService;
        $this->configPageLoader = $configPageLoader;
        $this->cartPageLoader = $cartPageLoader;
        $this->confirmPageLoader = $confirmPageLoader;
        $this->finishPageLoader = $finishPageLoader;
    }

    /**
     * @Route("/checkout", name="frontend.checkout.forward", options={"seo"="false"}, methods={"GET"})
     */
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('frontend.checkout.cart.page');
    }

    /**
     * @Route("/checkout/cart", name="frontend.checkout.cart.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function cart(InternalRequest $request, CheckoutContext $context): Response
    {
        $page = $this->cartPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/checkout/cart.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/config", name="frontend.checkout.config.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     */
    public function config(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->configPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/checkout/shipping_payment.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/confirm", name="frontend.checkout.confirm.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws CartTokenNotFoundException
     */
    public function confirm(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->confirmPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/checkout/confirm.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/checkout/finish", name="frontend.checkout.finish.page", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CustomerNotLoggedInException
     * @throws OrderNotFoundException
     * @throws InvalidParameterException
     * @throws \Shopware\Core\Framework\Exception\MissingParameterException
     */
    public function finish(InternalRequest $request, CheckoutContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->finishPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/checkout/finish.html.twig', ['page' => $page]);
    }
}
