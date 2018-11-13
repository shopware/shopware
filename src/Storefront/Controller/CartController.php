<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Cart\ProductCollector;
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
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('checkout_cart');
    }

    /**
     * @Route("/cart/product", name="cart.product.add", options={"seo"="false"}, methods={"POST"})
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     * @throws CartTokenNotFoundException
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

        if ($request->isXmlHttpRequest() && $this->acceptsHTML($request)) {
            return $this->renderStorefront(
                '@Storefront/frontend/checkout/ajax_cart.html.twig',
                ['cart' => $this->cartService->getCart($context->getToken(), $context)]
            );
        }

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/line-item", name="cart.line_item.add", options={"seo"="false"}, methods={"POST"})
     *
     * @throws MixedLineItemTypeException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws CartTokenNotFoundException
     */
    public function addLineItem(Request $request, CheckoutContext $context): Response
    {
        $identifier = $request->request->get('identifier');
        $quantity = $request->request->getInt('quantity', 1);
        $target = $request->request->get('target');
        $type = $request->request->get('type');
        $removable = $request->request->getBoolean('removable', null);
        $stackable = $request->request->getBoolean('stackable', null);

        if (!($identifier && $quantity && $type)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid identifier or quantity or type',
            ]);
        }

        $lineItem = new LineItem($identifier, $type, $quantity);
        $lineItem->setPayload(['id' => $identifier]);

        if ($removable !== null) {
            $lineItem->setRemovable($removable);
        }
        if ($stackable !== null) {
            $lineItem->setStackable($stackable);
        }

        $this->cartService->add($this->cartService->getCart($context->getToken(), $context), $lineItem, $context);

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/line-item", name="cart.line_item.delete", options={"seo"="false"}, methods={"POST"})
     *
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws CartTokenNotFoundException
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

        $cart = $this->cartService->remove($this->cartService->getCart($context->getToken(), $context), $identifier, $context);

        if ($request->isXmlHttpRequest() && $this->acceptsHTML($request)) {
            return $this->renderStorefront(
                '@Storefront/frontend/checkout/ajax_cart.html.twig',
                ['cart' => $cart]
            );
        }

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/line-item/quantity", name="cart.line_item.quantity.update", options={"seo"="false"}, methods={"POST"})
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws CartTokenNotFoundException
     */
    public function updateLineItemQuantity(Request $request, CheckoutContext $context): Response
    {
        $identifier = $request->request->get('identifier');
        $quantity = $request->request->getInt('quantity', 1);
        $target = $request->request->get('target');

        if (!($identifier && $quantity)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid identifier or quantity',
            ]);
        }

        try {
            $this->cartService->changeQuantity(
                $this->cartService->getCart($context->getToken(), $context),
                $identifier,
                $quantity,
                $context
            );
        } catch (LineItemNotFoundException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'LineItem not found',
            ]);
        }

        return $this->conditionalResponse($request, $target);
    }

    /**
     * @Route("/cart/amount", name="cart.amount.get", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function getCartAmount(CheckoutContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $amount = $this->renderStorefront(
            '@Storefront/frontend/checkout/ajax_amount.html.twig',
            ['amount' => $cart->getPrice()->getTotalPrice()]
        )->getContent();

        return new JsonResponse([
            'amount' => $amount,
            'quantity' => $cart->getLineItems()->count(),
        ]);
    }

    /**
     * @Route("/cart", name="cart_get_cart", options={"seo"="false"}, methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function getCart(CheckoutContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        return $this->renderStorefront(
            '@Storefront/frontend/checkout/ajax_cart.html.twig',
            ['cart' => $cart]
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

        if ($target === self::ROUTE_CHECKOUT_CONFIRM) {
            return $this->redirectToRoute(self::ROUTE_CHECKOUT_CONFIRM);
        }

        return $this->redirectToRoute(self::ROUTE_CHECKOUT_CART);
    }

    /**
     * @throws MixedLineItemTypeException
     * @throws InvalidQuantityException
     * @throws InvalidPayloadException
     * @throws CartTokenNotFoundException
     */
    private function addProductToCart(CheckoutContext $context, string $identifier, int $quantity, array $services = []): void
    {
        $key = $identifier;
        if (!empty($services)) {
            $services = array_values($services);
            $key = $identifier . '-' . implode('-', $services);
        }

        $lineItem = new LineItem($key, ProductCollector::LINE_ITEM_TYPE, $quantity);
        $lineItem->setPayload(['id' => $identifier, 'services' => implode('-', $services)])
            ->setStackable(true)
            ->setRemovable(true);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $this->cartService->add($cart, $lineItem, $context);
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
