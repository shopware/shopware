<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order\Api;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MissingOrderRelationException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\OrderRecalculationException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Rule\Rule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderRecalculationController extends AbstractController
{
    /**
     * @var RecalculationService
     */
    protected $recalculationService;

    public function __construct(RecalculationService $recalculationService)
    {
        $this->recalculationService = $recalculationService;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/order/{orderId}/recalculate", name="api.action.order.recalculate", methods={"POST"})
     *
     * @throws CustomerNotLoggedInException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws InvalidOrderException
     * @throws OrderRecalculationException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function recalculateOrder(string $orderId, Context $context): Response
    {
        $this->recalculationService->recalculateOrder($orderId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/order/{orderId}/product/{productId}", name="api.action.order.add-product", methods={"POST"})
     *
     * @throws DeliveryWithoutAddressException
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidOrderException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws OrderRecalculationException
     * @throws ProductNotFoundException
     * @throws MissingOrderRelationException
     */
    public function addProductToOrder(string $orderId, string $productId, Request $request, Context $context): Response
    {
        $quantity = $request->request->getInt('quantity', 1);
        $this->recalculationService->addProductToOrder($orderId, $productId, $quantity, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/order/{orderId}/creditItem", name="api.action.order.add-credit-item", methods={"POST"})
     *
     * */
    public function addCreditItemToOrder(string $orderId, Request $request, Context $context)
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
            throw new InvalidRequestParameterException('label');
        }

        if ($description !== null && !\is_string($description)) {
            throw new InvalidRequestParameterException('description');
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

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/order/{orderId}/lineItem", name="api.action.order.add-line-item", methods={"POST"})
     *
     * @throws DeliveryWithoutAddressException
     * @throws InvalidOrderException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws OrderRecalculationException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingOrderRelationException
     */
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

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/order-address/{orderAddressId}/customer-address/{customerAddressId}", name="api.action.order.replace-order-address", methods={"POST"})
     *
     * @throws OrderRecalculationException
     * @throws InconsistentCriteriaIdsException
     */
    public function replaceOrderAddressWithCustomerAddress(string $orderAddressId, string $customerAddressId, Context $context): JsonResponse
    {
        $this->recalculationService->replaceOrderAddressWithCustomerAddress($orderAddressId, $customerAddressId, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws InvalidPayloadException
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
            throw new InvalidRequestParameterException('label');
        }

        if ($description !== null && !\is_string($description)) {
            throw new InvalidRequestParameterException('description');
        }

        $lineItem->setLabel($label);
        $lineItem->setDescription($description);
        $lineItem->setRemovable($removeable);
        $lineItem->setStackable($stackable);
        $lineItem->setPayload($payload);

        $lineItem->setPriceDefinition(QuantityPriceDefinition::fromArray($priceDefinition));
    }
}
