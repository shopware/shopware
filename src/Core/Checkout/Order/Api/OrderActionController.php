<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Api;

use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class OrderActionController extends AbstractController
{
    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var StateMachineDefinition
     */
    private $stateMachineDefinition;

    public function __construct(OrderService $orderService, ApiVersionConverter $apiVersionConverter, StateMachineDefinition $stateMachineDefinition)
    {
        $this->orderService = $orderService;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->stateMachineDefinition = $stateMachineDefinition;
    }

    /**
     * @Route("/api/v{version}/_action/order/{orderId}/state/{transition}", name="api.action.order.state_machine.order.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
     */
    public function orderStateTransition(
        string $orderId,
        string $transition,
        int $version,
        Request $request,
        Context $context
    ): JsonResponse {
        $toPlace = $this->orderService->orderStateTransition(
            $orderId,
            $transition,
            $request->request,
            $context
        );

        $response = $this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace,
            $version
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/api/v{version}/_action/order_transaction/{orderTransactionId}/state/{transition}", name="api.action.order.state_machine.order_transaction.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
     */
    public function orderTransactionStateTransition(
        string $orderTransactionId,
        string $transition,
        int $version,
        Request $request,
        Context $context
    ): JsonResponse {
        $toPlace = $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            $transition,
            $request->request,
            $context
        );

        $response = $this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace,
            $version
        );

        return new JsonResponse($response);
    }

    /**
     * @Route("/api/v{version}/_action/order_delivery/{orderDeliveryId}/state/{transition}", name="api.action.order.state_machine.order_delivery.transition_state", methods={"POST"})
     *
     * @throws OrderNotFoundException
     */
    public function orderDeliveryStateTransition(
        string $orderDeliveryId,
        string $transition,
        int $version,
        Request $request,
        Context $context
    ): JsonResponse {
        $toPlace = $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            $transition,
            $request->request,
            $context
        );

        $response = $this->apiVersionConverter->convertEntity(
            $this->stateMachineDefinition,
            $toPlace,
            $version
        );

        return new JsonResponse($response);
    }
}
