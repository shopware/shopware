<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemCoverNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\Builder\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsCollector;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\Exception\ProductNumberNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CartLineItemController extends StorefrontController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var SalesChannelRepository
     */
    private $productRepository;

    public function __construct(
        CartService $cartService,
        TranslatorInterface $translator,
        EntityRepositoryInterface $mediaRepository,
        SalesChannelRepository $productRepository
    ) {
        $this->cartService = $cartService;
        $this->translator = $translator;
        $this->mediaRepository = $mediaRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @Route("/checkout/line-item/delete/{id}", name="frontend.checkout.line-item.delete", methods={"POST", "DELETE"}, defaults={"XmlHttpRequest": true})
     */
    public function removeLineItem(string $id, Request $request, SalesChannelContext $context): Response
    {
        try {
            $token = $request->request->getAlnum('token', $context->getToken());

            $cart = $this->cartService->getCart($token, $context);

            if (!$cart->has($id)) {
                throw new LineItemNotFoundException($id);
            }

            $this->cartService->remove($cart, $id, $context);

            $this->addFlash('success', $this->translator->trans('checkout.cartUpdateSuccess'));
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->translator->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/checkout/promotion/add", name="frontend.checkout.promotion.add", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function addPromotion(Request $request, SalesChannelContext $context): Response
    {
        try {
            /** @var string $token */
            $token = $request->request->getAlnum('token', $context->getToken());

            /** @var string|null $code */
            $code = $request->request->getAlnum('code');

            if ($code === null) {
                throw new \InvalidArgumentException('Code is required');
            }
            $lineItem = (new PromotionItemBuilder(CartPromotionsCollector::LINE_ITEM_TYPE))->buildPlaceholderItem(
                $code,
                $context->getContext()->getCurrencyPrecision()
            );
            $cart = $this->cartService->getCart($token, $context);

            $initialCartState = md5(json_encode($cart));

            $cart = $this->cartService->add($cart, $lineItem, $context);
            $cart->getErrors();
            if (!$this->hasPromotion($cart, $code)) {
                throw new LineItemNotFoundException($code);
            }
            $changedCartState = md5(json_encode($cart));

            if ($initialCartState !== $changedCartState) {
                $this->addFlash('success', $this->translator->trans('checkout.codeAddedSuccessful'));
            } else {
                $this->addFlash('info', $this->translator->trans('checkout.promotionAlreadyExistsInfo'));
            }
        } catch (LineItemNotFoundException $exception) {
            // todo this could have a multitude of reasons - imagine a code is valid but cannot be added because of restrictions
            // wouldn't it be the appropriate way to display the reason what avoided the promotion to be added
            $this->addFlash('warning', 'Gutschein-Code konnte nicht hinzugefügt werden - ist der Code falsch?');
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->translator->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/checkout/line-item/update/{id}", name="frontend.checkout.line-item.update", defaults={"XmlHttpRequest": true}, methods={"POST"})
     */
    public function updateLineItem(string $id, Request $request, SalesChannelContext $context): Response
    {
        try {
            $token = $request->request->getAlnum('token', $context->getToken());

            $quantity = $request->get('quantity');

            if ($quantity === null) {
                throw new \InvalidArgumentException('quantity field is required');
            }

            $cart = $this->cartService->getCart($token, $context);

            if (!$cart->has($id)) {
                throw new LineItemNotFoundException($id);
            }

            $this->cartService->changeQuantity($cart, $id, (int) $quantity, $context);

            $this->addFlash('success', $this->translator->trans('checkout.cartUpdateSuccess'));
        } catch (\Exception $exception) {
            $this->addFlash('danger', $this->translator->trans('error.message-default'));
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
            throw new ProductNumberNotFoundException($number);
        }

        $productId = array_shift($data);
        $request->request->add([
            'lineItems' => [
                $productId => [
                    'id' => $productId,
                    'quantity' => 1,
                    'type' => 'product',
                    'stackable' => true,
                    'removable' => true,
                ],
            ],
        ]);

        return $this->forward('Shopware\Storefront\PageController\CheckoutPageController::addLineItems');
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
    public function addLineItems(RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context): Response
    {
        /** @var RequestDataBag|null $lineItems */
        $lineItems = $requestDataBag->get('lineItems');
        if (!$lineItems) {
            throw new MissingRequestParameterException('lineItems');
        }

        $count = 0;

        $cart = $this->cartService->getCart($context->getToken(), $context);

        try {
            /** @var RequestDataBag $lineItemData */
            foreach ($lineItems as $lineItemData) {
                $lineItem = new LineItem(
                    $lineItemData->getAlnum('id'),
                    $lineItemData->getAlnum('type'),
                    $lineItemData->getInt('quantity', 1)
                );

                $this->updateLineItemByRequest($lineItem, $lineItemData, $context->getContext());

                $count += $lineItem->getQuantity();

                $this->cartService->add($cart, $lineItem, $context);
            }

            $this->addFlash('success', $this->translator->trans('checkout.addToCartSuccess', ['%count%' => $count]));
        } catch (ProductNotFoundException $exception) {
            $this->addFlash('danger', $this->translator->trans('error.addToCartError'));
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

    /**
     * @throws InvalidQuantityException
     * @throws LineItemCoverNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidPayloadException
     */
    private function updateLineItemByRequest(LineItem $lineItem, RequestDataBag $requestDataBag, Context $context): void
    {
        $quantity = (int) $requestDataBag->get('quantity');
        $payload = $requestDataBag->get('payload', []);
        $payload = array_replace_recursive(['id' => $lineItem->getKey()], $payload);
        $stackable = $requestDataBag->get('stackable');
        $removable = $requestDataBag->get('removable');
        $label = $requestDataBag->get('label');
        $description = $requestDataBag->get('description');
        $coverId = $requestDataBag->get('coverId');

        $lineItem->setPayload($payload);

        if ($quantity) {
            $lineItem->setQuantity($quantity);
        }

        if ($stackable !== null) {
            $lineItem->setStackable((bool) $stackable);
        }

        if ($removable !== null) {
            $lineItem->setRemovable((bool) $removable);
        }

        if ($label !== null) {
            $lineItem->setLabel($label);
        }

        if ($description !== null) {
            $lineItem->setDescription($description);
        }

        if ($coverId !== null) {
            $cover = $this->mediaRepository->search(new Criteria([$coverId]), $context)->get($coverId);

            if (!$cover) {
                throw new LineItemCoverNotFoundException($coverId, $lineItem->getKey());
            }

            $lineItem->setCover($cover);
        }
    }
}
