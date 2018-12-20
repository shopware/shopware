<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Controller\Widget;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @Route("/widgets/checkout/info", name="widgets/checkout/info", methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function infoAction(CheckoutContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        return $this->render('@Storefront/widgets/checkout/info.html.twig', [
            'cartQuantity' => $cart->getLineItems()->filterGoods()->count(),
            'cartAmount' => $cart->getPrice()->getTotalPrice(),
            'notesQuantity' => 0,
            'userLoggedIn' => false,
        ]);
    }
}
