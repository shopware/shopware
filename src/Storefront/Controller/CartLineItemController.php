<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\Builder\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsCollector;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartLineItemController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var SalesChannelRepository
     */
    private $productRepository;

    public function __construct(
        CartService $cartService,
        SalesChannelRepository $productRepository
    ) {
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
    }

    /**
     * @Route("/checkout/line-item/delete/{id}", name="frontend.checkout.line-item.delete", methods={"POST", "DELETE"}, defaults={"XmlHttpRequest": true})
     */
    public function deleteLineItem(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        try {
            if (!$cart->has($id)) {
                throw new LineItemNotFoundException($id);
            }

            $this->cartService->remove($cart, $id, $context);

            $this->addFlash('success', $this->trans('checkout.cartUpdateSuccess'));
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/checkout/promotion/add", name="frontend.checkout.promotion.add", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function addPromotion(Cart $cart, Request $request, SalesChannelContext $context): Response
    {
        try {
            /** @var string|null $code */
            $code = $request->request->getAlnum('code');

            if ($code === null) {
                throw new \InvalidArgumentException('Code is required');
            }
            $lineItem = (new PromotionItemBuilder(CartPromotionsCollector::LINE_ITEM_TYPE))->buildPlaceholderItem(
                $code,
                $context->getContext()->getCurrencyPrecision()
            );

            $initialCartState = md5(json_encode($cart));

            $cart = $this->cartService->add($cart, $lineItem, $context);
            $cart->getErrors();
            if (!$this->hasPromotion($cart, $code)) {
                throw new LineItemNotFoundException($code);
            }
            $changedCartState = md5(json_encode($cart));

            if ($initialCartState !== $changedCartState) {
                $this->addFlash('success', $this->trans('checkout.codeAddedSuccessful'));
            } else {
                $this->addFlash('info', $this->trans('checkout.promotionAlreadyExistsInfo'));
            }
        } catch (LineItemNotFoundException $exception) {
            // todo this could have a multitude of reasons - imagine a code is valid but cannot be added because of restrictions
            // wouldn't it be the appropriate way to display the reason what avoided the promotion to be added
            $this->addFlash('warning', $this->trans('error.message-default'));
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/checkout/line-item/change-quantity/{id}", name="frontend.checkout.line-item.change-quantity", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function changeQuantity(Cart $cart, string $id, Request $request, SalesChannelContext $context): Response
    {
        try {
            $quantity = $request->get('quantity');

            if ($quantity === null) {
                throw new \InvalidArgumentException('quantity field is required');
            }

            if (!$cart->has($id)) {
                throw new LineItemNotFoundException($id);
            }

            $this->cartService->changeQuantity($cart, $id, (int) $quantity, $context);

            $this->addFlash('success', $this->trans('checkout.cartUpdateSuccess'));
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/checkout/product/add-by-number", name="frontend.checkout.product.add-by-number", methods={"POST"})
     */
    public function addProductByNumber(Request $request, SalesChannelContext $context): Response
    {
        $number = $request->request->getAlnum('number');
        if (!$number) {
            throw new MissingRequestParameterException('number');
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('productNumber', $number));

        $idSearchResult = $this->productRepository->searchIds($criteria, $context);
        $data = $idSearchResult->getIds();

        if (empty($data)) {
            $this->addFlash('danger', $this->trans('error.productNotFound', ['%number%' => $number]));

            return $this->createActionResponse($request);
        }

        $productId = array_shift($data);
        $request->request->add([
            'lineItems' => [
                ['id' => $productId, 'quantity' => 1, 'type' => 'product'],
            ],
        ]);

        return $this->forwardToRoute('frontend.checkout.line-item.add');
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
     */
    public function addLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context): Response
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

                $this->cartService->add($cart, $lineItem, $context);
            }

            $this->addFlash('success', $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
        } catch (ProductNotFoundException $exception) {
            $this->addFlash('danger', $this->trans('error.addToCartError'));
        }

        return $this->createActionResponse($request);
    }

    private function hasPromotion(Cart $cart, string $code): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== CartPromotionsCollector::LINE_ITEM_TYPE) {
                continue;
            }

            $payload = $lineItem->getPayload();

            if (!array_key_exists('code', $payload)) {
                continue;
            }

            if ($code === $payload['code']) {
                return true;
            }
        }

        return false;
    }
}
