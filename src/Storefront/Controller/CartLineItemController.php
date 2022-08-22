<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
class CartLineItemController extends StorefrontController
{
    private CartService $cartService;

    private PromotionItemBuilder $promotionItemBuilder;

    private ProductLineItemFactory $productLineItemFactory;

    private HtmlSanitizer $htmlSanitizer;

    private AbstractProductListRoute $productListRoute;

    /**
     * @internal
     */
    public function __construct(
        CartService $cartService,
        PromotionItemBuilder $promotionItemBuilder,
        ProductLineItemFactory $productLineItemFactory,
        HtmlSanitizer $htmlSanitizer,
        AbstractProductListRoute $productListRoute
    ) {
        $this->cartService = $cartService;
        $this->promotionItemBuilder = $promotionItemBuilder;
        $this->productLineItemFactory = $productLineItemFactory;
        $this->htmlSanitizer = $htmlSanitizer;
        $this->productListRoute = $productListRoute;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/line-item/delete/{id}", name="frontend.checkout.line-item.delete", methods={"POST", "DELETE"}, defaults={"XmlHttpRequest": true})
     */
    public function deleteLineItem(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::delete-line-item', function () use ($cart, $id, $request, $context) {
            try {
                if (!$cart->has($id)) {
                    if (Feature::isActive('v6.5.0.0')) {
                        throw CartException::lineItemNotFound($id);
                    }

                    throw new LineItemNotFoundException($id);
                }

                $cart = $this->cartService->remove($cart, $id, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception $exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * @Since("6.0.0.0")
     * This is the storefront controller action for adding a promotion.
     * It has some individual code for the storefront layouts, like visual
     * error and success messages.
     *
     * @Route("/checkout/promotion/add", name="frontend.checkout.promotion.add", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function addPromotion(Cart $cart, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::add-promotion', function () use ($cart, $request, $context) {
            try {
                $code = (string) $request->request->get('code');

                if ($code === '') {
                    throw new \InvalidArgumentException('Code is required');
                }

                $lineItem = $this->promotionItemBuilder->buildPlaceholderItem($code);

                $cart = $this->cartService->add($cart, $lineItem, $context);

                // we basically show all cart errors or notices
                // at the moments its not possible to show success messages with "green" color
                // from the cart...thus it has to be done in the storefront level
                // so if we have an promotion added notice, we simply convert this to
                // a success flash message
                $addedEvents = $cart->getErrors()->filterInstance(PromotionCartAddedInformationError::class);
                if ($addedEvents->count() > 0) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.codeAddedSuccessful'));

                    return $this->createActionResponse($request);
                }

                // if we have no custom error message above
                // then simply continue with the default display
                // of the cart errors and notices
                $this->traceErrors($cart);
            } catch (\Exception $exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/line-item/change-quantity/{id}", name="frontend.checkout.line-item.change-quantity", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function changeQuantity(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::change-quantity', function () use ($cart, $id, $request, $context) {
            try {
                $quantity = $request->get('quantity');

                if ($quantity === null) {
                    throw new \InvalidArgumentException('quantity field is required');
                }

                if (!$cart->has($id)) {
                    if (Feature::isActive('v6.5.0.0')) {
                        throw CartException::lineItemNotFound($id);
                    }

                    throw new LineItemNotFoundException($id);
                }

                $cart = $this->cartService->changeQuantity($cart, $id, (int) $quantity, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception $exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/product/add-by-number", name="frontend.checkout.product.add-by-number", methods={"POST"})
     */
    public function addProductByNumber(Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::add-product-by-number', function () use ($request, $context) {
            $number = (string) $request->request->get('number');

            if (!$number) {
                throw new MissingRequestParameterException('number');
            }

            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('productNumber', $number));

            $data = $this->productListRoute->load($criteria, $context)->getProducts()->getIds();

            if (empty($data)) {
                $this->addFlash(self::DANGER, $this->trans(
                    'error.productNotFound',
                    ['%number%' => $this->htmlSanitizer->sanitize($number, null, true)]
                ));

                return $this->createActionResponse($request);
            }

            /** @var string $productId */
            $productId = array_shift($data);

            $product = $this->productLineItemFactory->create($productId);

            $cart = $this->cartService->getCart($context->getToken(), $context);

            $cart = $this->cartService->add($cart, $product, $context);

            if (!$this->traceErrors($cart)) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => 1]));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/checkout/line-item/add", name="frontend.checkout.line-item.add", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     *
     * requires the provided items in the following form
     * 'lineItems' => [
     *     'anyKey' => [
     *         'id' => 'someKey'
     *         'quantity' => 2,
     *         'type' => 'someType'
     *     ],
     *     'randomKey' => [
     *         'id' => 'otherKey'
     *         'quantity' => 2,
     *         'type' => 'otherType'
     *     ]
     * ]
     */
    public function addLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::add-line-item', function () use ($cart, $requestDataBag, $request, $context) {
            /** @var RequestDataBag|null $lineItems */
            $lineItems = $requestDataBag->get('lineItems');
            if (!$lineItems) {
                throw new MissingRequestParameterException('lineItems');
            }

            $count = 0;

            try {
                $items = [];
                /** @var RequestDataBag $lineItemData */
                foreach ($lineItems as $lineItemData) {
                    $lineItem = new LineItem(
                        $lineItemData->getAlnum('id'),
                        $lineItemData->getAlnum('type'),
                        $lineItemData->get('referencedId'),
                        $lineItemData->getInt('quantity', 1)
                    );

                    $lineItem->setStackable($lineItemData->getBoolean('stackable', true));
                    $lineItem->setRemovable($lineItemData->getBoolean('removable', true));

                    $count += $lineItem->getQuantity();

                    $items[] = $lineItem;
                }

                $cart = $this->cartService->add($cart, $items, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
                }
            } catch (ProductNotFoundException $exception) {
                $this->addFlash(self::DANGER, $this->trans('error.addToCartError'));
            }

            return $this->createActionResponse($request);
        });
    }

    private function traceErrors(Cart $cart): bool
    {
        if ($cart->getErrors()->count() <= 0) {
            return false;
        }

        $this->addCartErrors($cart, function (Error $error) {
            return $error->isPersistent();
        });

        return true;
    }
}
