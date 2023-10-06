<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Api;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('checkout')]
class OrderRecalculationController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(protected RecalculationService $recalculationService)
    {
    }

    #[Route(path: '/api/_action/order/{orderId}/recalculate', name: 'api.action.order.recalculate', methods: ['POST'])]
    public function recalculateOrder(string $orderId, Context $context): Response
    {
        $this->recalculationService->recalculateOrder($orderId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/order/{orderId}/product/{productId}', name: 'api.action.order.add-product', methods: ['POST'])]
    public function addProductToOrder(string $orderId, string $productId, Request $request, Context $context): Response
    {
        $quantity = $request->request->getInt('quantity', 1);
        $this->recalculationService->addProductToOrder($orderId, $productId, $quantity, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/order/{orderId}/creditItem', name: 'api.action.order.add-credit-item', methods: ['POST'])]
    public function addCreditItemToOrder(string $orderId, Request $request, Context $context): Response
    {
        $identifier = (string) $request->request->get('identifier');
        $type = LineItem::CREDIT_LINE_ITEM_TYPE;
        $quantity = $request->request->getInt('quantity', 1);

        $lineItem = new LineItem($identifier, $type, null, $quantity);
        $label = $request->request->get('label');
        $description = $request->request->get('description');
        $removeable = (bool) $request->request->get('removeable', true);
        $stackable = (bool) $request->request->get('stackable', true);
        $payload = $request->request->all('payload');
        $priceDefinition = $request->request->all('priceDefinition');

        if ($label !== null && !\is_string($label)) {
            throw RoutingException::invalidRequestParameter('label');
        }

        if ($description !== null && !\is_string($description)) {
            throw RoutingException::invalidRequestParameter('description');
        }

        $lineItem->setLabel($label);
        $lineItem->setDescription($description);
        $lineItem->setRemovable($removeable);
        $lineItem->setStackable($stackable);
        $lineItem->setPayload($payload);

        $lineItem->setPriceDefinition(
            new AbsolutePriceDefinition(
                (float) $priceDefinition['price'],
                new LineItemOfTypeRule(Rule::OPERATOR_NEQ, $type)
            )
        );

        $this->recalculationService->addCustomLineItem($orderId, $lineItem, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/order/{orderId}/lineItem', name: 'api.action.order.add-line-item', methods: ['POST'])]
    public function addCustomLineItemToOrder(string $orderId, Request $request, Context $context): Response
    {
        $identifier = (string) $request->request->get('identifier');
        $type = $request->request->get('type', LineItem::CUSTOM_LINE_ITEM_TYPE);
        $quantity = $request->request->getInt('quantity', 1);

        $lineItem = (new LineItem($identifier, (string) $type, null, $quantity))
            ->setStackable(true)
            ->setRemovable(true);
        $this->updateLineItemByRequest($request, $lineItem);

        $this->recalculationService->addCustomLineItem($orderId, $lineItem, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/order/{orderId}/promotion-item', name: 'api.action.order.add-promotion-item', methods: ['POST'])]
    public function addPromotionItemToOrder(string $orderId, Request $request, Context $context): Response
    {
        $code = (string) $request->request->get('code');

        $cart = $this->recalculationService->addPromotionLineItem($orderId, $code, $context);

        return new CartResponse($cart);
    }

    #[Route(path: '/api/_action/order/{orderId}/toggleAutomaticPromotions', name: 'api.action.order.toggle-automatic-promotions', methods: ['POST'])]
    public function toggleAutomaticPromotions(string $orderId, Request $request, Context $context): Response
    {
        $skipAutomaticPromotions = (bool) $request->request->get('skipAutomaticPromotions', true);

        $cart = $this->recalculationService->toggleAutomaticPromotion($orderId, $context, $skipAutomaticPromotions);

        return new CartResponse($cart);
    }

    #[Route(path: '/api/_action/order-address/{orderAddressId}/customer-address/{customerAddressId}', name: 'api.action.order.replace-order-address', methods: ['POST'])]
    public function replaceOrderAddressWithCustomerAddress(string $orderAddressId, string $customerAddressId, Context $context): JsonResponse
    {
        $this->recalculationService->replaceOrderAddressWithCustomerAddress($orderAddressId, $customerAddressId, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws CartException
     */
    private function updateLineItemByRequest(Request $request, LineItem $lineItem): void
    {
        $label = $request->request->get('label');
        $description = $request->request->get('description');
        $removeable = (bool) $request->request->get('removeable', true);
        $stackable = (bool) $request->request->get('stackable', true);
        $payload = $request->request->all('payload');
        $priceDefinition = $request->request->all('priceDefinition');

        if ($label !== null && !\is_string($label)) {
            throw RoutingException::invalidRequestParameter('label');
        }

        if ($description !== null && !\is_string($description)) {
            throw RoutingException::invalidRequestParameter('description');
        }

        $lineItem->setLabel($label);
        $lineItem->setDescription($description);
        $lineItem->setRemovable($removeable);
        $lineItem->setStackable($stackable);
        $lineItem->setPayload($payload);

        $lineItem->setPriceDefinition(QuantityPriceDefinition::fromArray($priceDefinition));
    }
}
