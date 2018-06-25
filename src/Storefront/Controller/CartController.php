<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Cart\ProductProcessor;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends StorefrontController
{
    /**
     * Route name to cart index action
     */
    public const ROUTE_CHECKOUT_CART = 'checkout_cart';

    /**
     * Route name to checkout confirm action
     */
    public const ROUTE_CHECKOUT_CONFIRM = 'checkout_confirm';

    /**
     * ONLY WORK IN PROGRESS
     */
    public const USER_LOGGED_IN = true;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @Route("/cart", name="cart_index", options={"seo"="false"})
     */
    public function index()
    {
        return $this->redirectToRoute('checkout_cart');
    }

    /**
     * @Route("/cart/addProduct", name="cart_add_product", options={"seo"="false"})
     * @Method({"POST"})
     */
    public function addProduct(Request $request, CheckoutContext $context): Response
    {
        $identifier = $request->request->get('identifier');
        $quantity = $request->request->getInt('quantity');
        $target = $request->request->get('target');
        $services = $request->request->get('service', []);

        if (!($identifier && $quantity)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid identifier or quantity',
            ]);
        }

        $this->addProductToCart($context, $identifier, $quantity, $services);

        if ($this->acceptsHTML($request)) {
            return $this->renderStorefront(
                '@Storefront/frontend/checkout/ajax_cart.html.twig',
                ['cart' => $this->cartService->getCalculatedCart($context)]
            );
        }

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/removeLineItem", name="cart_delete_line_item", options={"seo"="false"})
     * @Method({"POST"})
     */
    public function removeLineItem(Request $request, CheckoutContext $context): Response
    {
        $identifier = $request->request->get('identifier');
        $target = $request->request->get('target');

        if (!$identifier) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid identifier',
            ]);
        }

        $this->cartService->remove($identifier, $context);

        if ($this->acceptsHTML($request)) {
            return $this->renderStorefront(
                '@Storefront/frontend/checkout/ajax_cart.html.twig',
                ['cart' => $this->cartService->getCalculatedCart($context)]
            );
        }

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/setLineItemQuantity", name="cart_set_line_item_quantity", options={"seo"="false"})
     * @Method({"POST"})
     */
    public function setLineItemQuantity(Request $request, CheckoutContext $context): Response
    {
        $identifier = $request->request->get('identifier');
        $quantity = $request->request->getInt('quantity');
        $target = $request->request->get('target');

        if (!($identifier && $quantity)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid identifier or quantity',
            ]);
        }

        try {
            $this->cartService->changeQuantity($identifier, $quantity, $context);
        } catch (LineItemNotFoundException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'LineItem not found',
            ]);
        }

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/addVoucher", name="cart_add_voucher", options={"seo"="false"})
     * @Method({"POST"})
     */
    public function addVoucher(Request $request, CheckoutContext $context): Response
    {
        $identifier = $request->request->get('identifier', false);
        $target = $request->request->get('target');

        if (!$identifier) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid identifier',
            ]);
        }

        $this->cartService->add(
            new LineItem($identifier, 'voucher', 1, ['code' => $identifier]),
            $context
        );

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/getAmount", name="cart_get_amount", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws \Exception
     */
    public function getCartAmount(Request $request, CheckoutContext $context): Response
    {
        $calculatedCart = $this->cartService->getCalculatedCart($context);

        $amount = $this->renderStorefront(
            '@Storefront/frontend/checkout/ajax_amount.html.twig',
            ['amount' => $calculatedCart->getPrice()->getTotalPrice()]
        )->getContent();

        return new JsonResponse([
            'amount' => $amount,
            'quantity' => $calculatedCart->getCalculatedLineItems()->count(),
        ]);
    }

    /**
     * @Route("/cart/getCart", name="cart_get_cart", options={"seo"="false"})
     * @Method({"POST"})
     *
     * @throws \Exception
     */
    public function getCart(Request $request, CheckoutContext $context): Response
    {
        $calculatedCart = $this->cartService->getCalculatedCart($context);

        return $this->renderStorefront(
            '@Storefront/frontend/checkout/ajax_cart.html.twig',
            ['cart' => $calculatedCart]
        );
    }

    /**
     * Serve response depending on target and current user state
     *
     * @return JsonResponse|RedirectResponse|Response
     */
    private function conditionalResponse(Request $request, ?string $target): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        if ($target == self::ROUTE_CHECKOUT_CONFIRM) {
            return $this->redirectToRoute(self::ROUTE_CHECKOUT_CONFIRM);
        }

        return $this->redirectToRoute(self::ROUTE_CHECKOUT_CART);
    }

    private function addProductToCart(CheckoutContext $context, string $identifier, int $quantity, array $services = []): void
    {
        $key = $identifier;
        if (!empty($services)) {
            $services = array_values($services);
            $key = $identifier . '-' . implode('-', $services);
        }

        $this->cartService->add(
            new LineItem(
                $key,
                ProductProcessor::TYPE_PRODUCT,
                $quantity,
                ['id' => $identifier, 'services' => $services]
            ),
            $context
        );
    }

    private function acceptsHTML(Request $request): bool
    {
        $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'));
        if ($acceptHeader->has('text/html')) {
            return true;
        }

        return false;
    }
}
