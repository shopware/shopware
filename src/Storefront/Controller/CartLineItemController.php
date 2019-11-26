<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CartLineItemController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var PromotionItemBuilder
     */
    private $promotionItemBuilder;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductLineItemFactory
     */
    private $productLineItemFactory;

    public function __construct(
        CartService $cartService,
        SalesChannelRepositoryInterface $productRepository,
        PromotionItemBuilder $promotionItemBuilder,
        ProductLineItemFactory $productLineItemFactory
    ) {
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
        $this->promotionItemBuilder = $promotionItemBuilder;
        $this->productLineItemFactory = $productLineItemFactory;
    }

    /**
     * @Route("/checkout/line-item/delete/{id}", name="frontend.checkout.line-item.delete", methods={"POST", "DELETE"}, defaults={"XmlHttpRequest": true})
     */
    public function deleteLineItem(Cart $cart, string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            if (!$cart->has($id)) {
                throw new LineItemNotFoundException($id);
            }

            $cart = $this->cartService->remove($cart, $id, $salesChannelContext);

            if (!$this->traceErrors($cart)) {
                $this->addFlash('success', $this->trans('checkout.cartUpdateSuccess'));
            }
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * This is the storefront controller action for adding a promotion.
     * It has some individual code for the storefront layouts, like visual
     * error and success messages.
     *
     * @Route("/checkout/promotion/add", name="frontend.checkout.promotion.add", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function addPromotion(Cart $cart, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            /** @var string|null $code */
            $code = $request->request->get('code');

            if ($code === null) {
                throw new \InvalidArgumentException('Code is required');
            }

            $lineItem = $this->promotionItemBuilder->buildPlaceholderItem($code, $salesChannelContext->getContext()->getCurrencyPrecision());

            $initialCartState = md5(json_encode($cart));

            $cart = $this->cartService->add($cart, $lineItem, $salesChannelContext);

            $this->traceErrors($cart);

            $changedCartState = md5(json_encode($cart));

            // if we do not a valid promotion line item
            // but the cart has been added, let the user know...
            // IMPORTANT: this has to be always shown, even if the cart changes!!!
            if ($this->codeExistsInCart($code) && !$this->hasPromotion($cart, $code)) {
                $this->addFlash('info', $this->trans('checkout.promotionExistsButRulesDoNotMatch'));

                return $this->createActionResponse($request);
            }

            if ($initialCartState !== $changedCartState) {
                // cart has really changed, so lets show a success
                $this->addFlash('success', $this->trans('checkout.codeAddedSuccessful'));
            } elseif ($this->codeExistsInCart($code) && !$this->hasPromotion($cart, $code)) {
                // if we do not a valid promotion line item
                // but the cart has been added, let the user know...
                $this->addFlash('info', $this->trans('checkout.promotionExistsButRulesDoNotMatch'));
            } elseif ($this->hasPromotion($cart, $code)) {
                // if cart has not changed and we have that promotion
                // then its added one more time
                $this->addFlash('info', $this->trans('checkout.promotionAlreadyExistsInfo'));
            } else {
                $this->addFlash('warning', $this->trans('error.message-default'));
            }
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/checkout/line-item/change-quantity/{id}", name="frontend.checkout.line-item.change-quantity", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function changeQuantity(Cart $cart, string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $quantity = $request->get('quantity');

            if ($quantity === null) {
                throw new \InvalidArgumentException('quantity field is required');
            }

            if (!$cart->has($id)) {
                throw new LineItemNotFoundException($id);
            }

            $cart = $this->cartService->changeQuantity($cart, $id, (int) $quantity, $salesChannelContext);

            if (!$this->traceErrors($cart)) {
                $this->addFlash('success', $this->trans('checkout.cartUpdateSuccess'));
            }
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/checkout/product/add-by-number", name="frontend.checkout.product.add-by-number", methods={"POST"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function addProductByNumber(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $number = $request->request->getAlnum('number');
        if (!$number) {
            throw new MissingRequestParameterException('number');
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('productNumber', $number));

        $idSearchResult = $this->productRepository->searchIds($criteria, $salesChannelContext);
        $data = $idSearchResult->getIds();

        if (empty($data)) {
            $this->addFlash('danger', $this->trans('error.productNotFound', ['%number%' => $number]));

            return $this->createActionResponse($request);
        }

        $productId = array_shift($data);

        $product = $this->productLineItemFactory->create($productId);

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $cart = $this->cartService->add($cart, $product, $salesChannelContext);

        if (!$this->traceErrors($cart)) {
            $this->addFlash('success', $this->trans('checkout.addToCartSuccess', ['%count%' => 1]));
        }

        return $this->createActionResponse($request);
    }

    /**
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
     *
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MissingRequestParameterException
     * @throws MixedLineItemTypeException
     */
    public function addLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        /** @var RequestDataBag|null $lineItems */
        $lineItems = $requestDataBag->get('lineItems');
        if (!$lineItems) {
            throw new MissingRequestParameterException('lineItems');
        }

        $count = 0;

        try {
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

                $cart = $this->cartService->add($cart, $lineItem, $salesChannelContext);
            }

            if (!$this->traceErrors($cart)) {
                $this->addFlash('success', $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
            }
        } catch (ProductNotFoundException $exception) {
            $this->addFlash('danger', $this->trans('error.addToCartError'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * This function verifies if our cart has the provided promotion.
     * This is necessary to see if adding the code did work in the end.
     */
    private function hasPromotion(Cart $cart, string $code): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }

            if ($code === $lineItem->getReferencedId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * function validates if a code has at least been added
     * to our cart.
     */
    private function codeExistsInCart(string $code): bool
    {
        /** @var array $allCodes */
        $allCodes = $this->container->get('session')->get(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES);

        return in_array($code, $allCodes, true);
    }

    private function traceErrors(Cart $cart): bool
    {
        if ($cart->getErrors()->count() <= 0) {
            return false;
        }

        foreach ($cart->getErrors() as $error) {
            $type = 'danger';

            if ($error->getLevel() === Error::LEVEL_NOTICE) {
                $type = 'info';
            }

            $parameters = [];
            foreach ($error->getParameters() as $key => $value) {
                $parameters['%' . $key . '%'] = $value;
            }

            $message = $this->trans('checkout.' . $error->getMessageKey(), $parameters);

            $this->addFlash($type, $message);
        }

        $cart->getErrors()->clear();

        return true;
    }
}
