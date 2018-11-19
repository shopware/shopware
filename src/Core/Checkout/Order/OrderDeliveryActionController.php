<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\StateMachine\IllegalTransitionException;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateStruct;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionStruct;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderDeliveryActionController extends AbstractController
{
    /**
     * @var RepositoryInterface
     */
    private $orderDeliveryRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(RepositoryInterface $orderDeliveryRepository, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/order-delivery/{deliveryId}/actions/state", name="api.order_delivery.get_state", methods={"GET"})
     */
    public function getAvailableTransitions(Request $request, Context $context, string $deliveryId): Response
    {
        $delivery = $this->getOrderDelivery($deliveryId, $context);
        $stateMachine = $this->stateMachineRegistry->getStateMachine(Defaults::ORDER_DELIVERY_STATE_MACHINE, $context);

        $transitions = [];
        $baseUrl = $this->generateUrl('api.order_delivery.transition_state', [
            'deliveryId' => $delivery->getId(),
            'version' => $request->get('version'),
        ]);

        $currentState = null;

        /** @var StateMachineStateStruct $place */
        foreach ($stateMachine->getStates()->getElements() as $place) {
            if ($place->getTechnicalName() === $delivery->getState()->getTechnicalName()) {
                $currentState = [
                    'name' => $place->getName(),
                    'technicalName' => $place->getTechnicalName(),
                ];
            }
        }

        /** @var StateMachineTransitionStruct $transition */
        foreach ($stateMachine->getTransitions()->getElements() as $transition) {
            if ($transition->getFromState()->getTechnicalName() !== $delivery->getState()->getTechnicalName()) {
                continue;
            }

            $transitions[] = [
                'name' => $transition->getToState()->getName(),
                'technicalName' => $transition->getToState()->getTechnicalName(),
                'actionName' => $transition->getActionName(),
                'url' => $baseUrl . '/' . $transition->getActionName(),
            ];
        }

        return new JsonResponse([
            'currentState' => $currentState,
            'transitions' => $transitions,
        ]);
    }

    /**
     * @Route("/api/v{version}/order-delivery/{deliveryId}/actions/state/{transition?}", name="api.order_delivery.transition_state", methods={"POST"})
     */
    public function transitionOrderState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $deliveryId,
        string $transition = null
    ): Response {
        $delivery = $this->getOrderDelivery($deliveryId, $context);

        if (empty($transition)) {
            $transitions = $this->stateMachineRegistry->getAvailableTransitions(Defaults::ORDER_DELIVERY_STATE_MACHINE, $delivery->getState()->getTechnicalName(), $context);
            $transitionNames = array_map(function (StateMachineTransitionStruct $transition) {
                return $transition->getActionName();
            }, $transitions);

            throw new IllegalTransitionException($delivery->getState()->getName(), '', $transitionNames);
        }

        $toPlace = $this->stateMachineRegistry->transition(Defaults::ORDER_DELIVERY_STATE_MACHINE, $delivery->getState()->getTechnicalName(), $transition, $context);

        $payload = [
            ['id' => $delivery->getId(), 'stateId' => $toPlace->getId()],
        ];

        $this->orderDeliveryRepository->update($payload, $context);

        $delivery->setState($toPlace);
        $delivery->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($delivery, OrderDeliveryDefinition::class, $request, $context);
    }

    private function getOrderDelivery(string $id, Context $context): OrderDeliveryStruct
    {
        $result = $this->orderDeliveryRepository->read(new ReadCriteria([$id]), $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException(OrderDeliveryDefinition::getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
