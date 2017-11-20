<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\CartBridge\Service\StoreFrontCartService;
use Shopware\Context\Struct\ShopContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends StorefrontController
{
    /**
     * @var StoreFrontCartService
     */
    private $cartService;

    public function __construct(StoreFrontCartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @Route("/checkout", name="checkout_index", options={"seo"="false"})
     */
    public function indexAction()
    {
        return $this->redirectToRoute('checkout_cart');
    }

    /**
     * @Route("/checkout/cart", name="checkout_cart", options={"seo"="false"})
     */
    public function cartAction(): Response
    {
        return $this->renderStorefront('@Storefront/frontend/checkout/cart.html.twig', [
            'cart' => $this->cartService->getCart(),
        ]);
    }

    /**
     * @Route("/checkout/confirm", name="checkout_confirm", options={"seo"="false"})
     *
     * @param ShopContext $context
     *
     * @return RedirectResponse|Response
     */
    public function confirmAction(ShopContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('account_login');
        }
        if ($this->cartService->getCart()->getCalculatedCart()->getCalculatedLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        return $this->renderStorefront('@Storefront/frontend/checkout/confirm.html.twig', [
            'cart' => $this->cartService->getCart(),
            'customer' => $context->getCustomer(),
        ]);
    }

    /**
     * @Route("/checkout/finish", name="checkout_finish", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @param ShopContext $context
     *
     * @return RedirectResponse|Response
     */
    public function finishAction(ShopContext $context): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('account_login');
        }
        if ($this->cartService->getCart()->getCalculatedCart()->getCalculatedLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        $cart = $this->cartService->getCart();
        $clonedCart = clone $cart;
        $this->cartService->order();

        return $this->renderStorefront('@Storefront/frontend/checkout/finish.html.twig', [
            'cart' => $clonedCart,
            'customer' => $context->getCustomer(),
        ]);
    }
}
