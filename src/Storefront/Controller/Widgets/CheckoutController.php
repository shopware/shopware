<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller\Widgets;

use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Controller\StorefrontController;
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
     */
    public function infoAction(CheckoutContext $context)
    {
        $cart = $this->cartService->getCart($context);

        return $this->render('@Storefront/widgets/checkout/info.html.twig', [
            'cartQuantity' => $cart->getLineItems()->filterGoods()->count(),
            'cartAmount' => $cart->getPrice()->getTotalPrice(),
            'sNotesQuantity' => 0,
            'sUserLoggedIn' => false,
        ]);
    }
}
