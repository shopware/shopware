<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class CartLineItemController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly PromotionItemBuilder $promotionItemBuilder,
        private readonly ProductLineItemFactory $productLineItemFactory,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly AbstractProductListRoute $productListRoute,
        private readonly LineItemFactoryRegistry $lineItemFactoryRegistry
    ) {
    }

    #[Route(path: '/checkout/line-item/delete/{id}', name: 'frontend.checkout.line-item.delete', defaults: ['XmlHttpRequest' => true], methods: ['POST', 'DELETE'])]
    public function deleteLineItem(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::delete-line-item', function () use ($cart, $id, $request, $context) {
            try {
                if (!$cart->has($id)) {
                    throw CartException::lineItemNotFound($id);
                }

                $cart = $this->cartService->remove($cart, $id, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * requires the provided items in the following form
     * 'ids' => [
     *     'firstLineItemId',
     *     'secondLineItemId',
     *     'thirdLineItemId',
     * ]
     */
    #[Route(path: '/checkout/line-item/delete', name: 'frontend.checkout.line-items.delete', defaults: ['XmlHttpRequest' => true], methods: ['POST', 'DELETE'])]
    public function deleteLineItems(Cart $cart, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::delete-line-items', function () use ($cart, $request, $context) {
            try {
                $idData = $request->get('ids');
                if (!\is_array($idData) || $idData === []) {
                    throw RoutingException::missingRequestParameter('ids');
                }

                $ids = [];
                foreach ($idData as $key => $id) {
                    if (!\is_string($id)) {
                        throw RoutingException::invalidRequestParameter("ids[{$key}]");
                    }

                    $ids[] = $id;
                }

                $cart = $this->cartService->removeItems($cart, $ids, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * It has some individual code for the storefront layouts, like visual
     * error and success messages.
     */
    #[Route(path: '/checkout/promotion/add', name: 'frontend.checkout.promotion.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addPromotion(Cart $cart, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::add-promotion', function () use ($cart, $request, $context) {
            try {
                $code = (string) $request->request->get('code');

                if ($code === '') {
                    throw RoutingException::missingRequestParameter('code');
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
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    #[Route(path: '/checkout/line-item/change-quantity/{id}', name: 'frontend.checkout.line-item.change-quantity', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function changeQuantity(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::change-quantity', function () use ($cart, $id, $request, $context) {
            try {
                $quantity = $request->get('quantity');

                if ($quantity === null) {
                    throw RoutingException::missingRequestParameter('quantity');
                }

                if (!$cart->has($id)) {
                    throw CartException::lineItemNotFound($id);
                }

                $cart = $this->cartService->changeQuantity($cart, $id, (int) $quantity, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
     * requires the provided items in the following form
     * 'lineItems' => [
     *     'anyKey' => [
     *         'id' => 'someKey'
     *         'quantity' => 2,
     *     ],
     *     'randomKey' => [
     *         'id' => 'otherKey'
     *         'quantity' => 2,
     *     ]
     * ]
     */
    #[Route(path: '/checkout/line-item/update', name: 'frontend.checkout.line-items.update', defaults: ['XmlHttpRequest' => true], methods: ['POST', 'PATCH'])]
    public function updateLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::update-line-items', function () use ($cart, $requestDataBag, $request, $context) {
            try {
                $lineItems = $requestDataBag->get('lineItems');
                if (!$lineItems instanceof RequestDataBag) {
                    throw RoutingException::missingRequestParameter('lineItems');
                }

                $items = [];
                foreach ($lineItems as $lineItemData) {
                    $items[] = $this->getLineItemArray($lineItemData, null);
                }

                $this->cartService->update($cart, $items, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
                }
            } catch (\Exception) {
                $this->addFlash(self::DANGER, $this->trans('error.message-default'));
            }

            return $this->createActionResponse($request);
        });
    }

    #[Route(path: '/checkout/product/add-by-number', name: 'frontend.checkout.product.add-by-number', methods: ['POST'])]
    public function addProductByNumber(Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::add-product-by-number', function () use ($request, $context) {
            $number = (string) $request->request->get('number');

            if (!$number) {
                throw RoutingException::missingRequestParameter('number');
            }

            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('productNumber', $number));
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('childCount', 0),
                new EqualsFilter('childCount', null),
            ]));

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

            $product = $this->productLineItemFactory->create(['id' => $productId, 'referencedId' => $productId], $context);

            $cart = $this->cartService->getCart($context->getToken(), $context);

            $cart = $this->cartService->add($cart, $product, $context);

            if (!$this->traceErrors($cart)) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => 1]));
            }

            return $this->createActionResponse($request);
        });
    }

    /**
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
    #[Route(path: '/checkout/line-item/add', name: 'frontend.checkout.line-item.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context): Response
    {
        return Profiler::trace('cart::add-line-item', function () use ($cart, $requestDataBag, $request, $context) {
            /** @var RequestDataBag|null $lineItems */
            $lineItems = $requestDataBag->get('lineItems');
            if (!$lineItems) {
                throw RoutingException::missingRequestParameter('lineItems');
            }

            $count = 0;

            try {
                $items = [];
                /** @var RequestDataBag $lineItemData */
                foreach ($lineItems as $lineItemData) {
                    try {
                        $item = $this->lineItemFactoryRegistry->create($this->getLineItemArray($lineItemData, [
                            'quantity' => 1,
                            'stackable' => true,
                            'removable' => true,
                        ]), $context);
                        $count += $item->getQuantity();

                        $items[] = $item;
                    } catch (CartException $e) {
                        if ($e->getErrorCode() === CartException::CART_INVALID_LINE_ITEM_QUANTITY_CODE) {
                            $this->addFlash(
                                self::DANGER,
                                $this->trans(
                                    'error.CHECKOUT__CART_INVALID_LINE_ITEM_QUANTITY',
                                    [
                                        '%quantity%' => $e->getParameter('quantity'),
                                    ]
                                )
                            );

                            return $this->createActionResponse($request);
                        }

                        throw $e;
                    }
                }

                $cart = $this->cartService->add($cart, $items, $context);

                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
                }
            } catch (ProductNotFoundException|RoutingException) {
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

        $this->addCartErrors($cart, fn (Error $error) => $error->isPersistent());

        return true;
    }

    /**
     * @param array{quantity?: int, stackable?: bool, removable?: bool} $defaultValues
     *
     * @return array<string|int, mixed>
     */
    private function getLineItemArray(RequestDataBag $lineItemData, ?array $defaultValues): array
    {
        if ($lineItemData->has('payload')) {
            $payload = $lineItemData->get('payload');

            if (mb_strlen($payload, '8bit') > (1024 * 256)) {
                throw RoutingException::invalidRequestParameter('payload');
            }

            $lineItemData->set('payload', json_decode($payload, true, 512, \JSON_THROW_ON_ERROR));
        }
        $lineItemArray = $lineItemData->all();

        if (isset($lineItemArray['quantity'])) {
            $lineItemArray['quantity'] = (int) $lineItemArray['quantity'];
        } elseif (isset($defaultValues['quantity'])) {
            $lineItemArray['quantity'] = $defaultValues['quantity'];
        }

        if (isset($lineItemArray['stackable'])) {
            $lineItemArray['stackable'] = (bool) $lineItemArray['stackable'];
        } elseif (isset($defaultValues['stackable'])) {
            $lineItemArray['stackable'] = $defaultValues['stackable'];
        }

        if (isset($lineItemArray['removable'])) {
            $lineItemArray['removable'] = (bool) $lineItemArray['removable'];
        } elseif (isset($defaultValues['removable'])) {
            $lineItemArray['removable'] = $defaultValues['removable'];
        }

        if (isset($lineItemArray['priceDefinition']) && isset($lineItemArray['priceDefinition']['quantity'])) {
            $lineItemArray['priceDefinition']['quantity'] = (int) $lineItemArray['priceDefinition']['quantity'];
        }

        if (isset($lineItemArray['priceDefinition']) && isset($lineItemArray['priceDefinition']['isCalculated'])) {
            $lineItemArray['priceDefinition']['isCalculated'] = (int) $lineItemArray['priceDefinition']['isCalculated'];
        }

        return $lineItemArray;
    }
}
