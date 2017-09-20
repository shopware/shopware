<?php

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\ShopContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends FrontendController
{
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
        $cartService = $this->get('shopware.cart.storefront_service');

        return $this->render('frontend/checkout/cart.html.twig', [
            'cart' => $cartService->getCart(),
        ]);
    }

    /**
     * @Route("/checkout/confirm", name="checkout_confirm", options={"seo"="false"})
     * @param ShopContext $context
     * @return RedirectResponse|Response
     */
    public function confirmAction(ShopContext $context): Response
    {
        $cartService = $this->get('shopware.cart.storefront_service');

        if (!$context->getCustomer()) {
            return $this->redirectToRoute('checkout_cart');
        }
        if ($cartService->getCart()->getCalculatedCart()->getCalculatedLineItems()->count() === 0) {
            return $this->redirectToRoute('checkout_cart');
        }

        return $this->render('frontend/checkout/confirm.html.twig', [
            'cart' => $cartService->getCart(),
            'customer' => $context->getCustomer(),
        ]);
    }

    /**
     * @Route("/checkout/finish", name="checkout_finish", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @param Request $request
     * @param ShopContext $context
     * @return RedirectResponse
     */
    public function finishAction(Request $request, ShopContext $context): RedirectResponse
    {
        $cartService = $this->get('shopware.cart.storefront_service');
        $cartService->order();

        return $this->redirectToRoute('homepage');
//        return $this->render('frontend/checkout/finish.html.twig', [
//            'cart' => $cartService->getCart(),
//            'customer' => $context->getCustomer()
//        ]);
    }
}