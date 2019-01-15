<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutPageletController extends StorefrontController
{
    /**
     * @var CartInfoPageletLoader
     */
    private $cartInfoPageletLoader;

    public function __construct(CartInfoPageletLoader $cartInfoPageletLoader)
    {
        $this->cartInfoPageletLoader = $cartInfoPageletLoader;
    }

    /**
     * @Route("/widgets/checkout/info", name="widgets/checkout/info", methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     * @throws \Twig_Error_Loader
     */
    public function infoAction(CartInfoPageletRequest $request, CheckoutContext $context): Response
    {
        $page = $this->cartInfoPageletLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/checkout/info.html.twig', [
            'page' => [
                'cartInfo' => $page,
                ],
            ]);
    }
}
