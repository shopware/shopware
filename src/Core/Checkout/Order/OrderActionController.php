<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\StateMachine\IllegalTransitionException;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderActionController extends AbstractController
{
    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(EntityRepository $orderRepository, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderRepository = $orderRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/_action/order/{orderId}/state", name="api.action.order.get-state", methods={"GET"})
     */
    public function getAvailableTransitions(Request $request, Context $context, string $orderId): Response
    {
        $order = $this->getOrder($orderId, $context);
        $stateMachine = $this->stateMachineRegistry->getStateMachine(Defaults::ORDER_STATE_MACHINE, $context);

        $transitions = [];
        $baseUrl = $this->generateUrl('api.order.transition_state', [
            'orderId' => $order->getId(),
            'version' => $request->get('version'),
        ]);

        $currentState = null;

        /** @var StateMachineStateEntity $place */
        foreach ($stateMachine->getStates()->getElements() as $place) {
            if ($place->getTechnicalName() === $order->getStateMachineState()->getTechnicalName()) {
                $currentState = [
                    'name' => $place->getName(),
                    'technicalName' => $place->getTechnicalName(),
                ];
            }
        }

        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachine->getTransitions()->getElements() as $transition) {
            if ($transition->getFromStateMachineState()->getTechnicalName() !== $order->getStateMachineState()->getTechnicalName()) {
                continue;
            }

            $transitions[] = [
                'name' => $transition->getToStateMachineState()->getName(),
                'technicalName' => $transition->getToStateMachineState()->getTechnicalName(),
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
     * @Route("/api/v{version}/_action/order/{orderId}/state/{transition?}", name="api.order.transition_state", methods={"POST"})
     */
    public function transitionOrderState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $orderId,
        string $transition = null
    ): Response {
        $order = $this->getOrder($orderId, $context);

        if (empty($transition)) {
            $transitions = $this->stateMachineRegistry->getAvailableTransitions(Defaults::ORDER_STATE_MACHINE, $order->getStateMachineState()->getTechnicalName(), $context);
            $transitionNames = array_map(function (StateMachineTransitionEntity $transition) {
                return $transition->getActionName();
            }, $transitions);

            throw new IllegalTransitionException($order->getStateMachineState()->getName(), '', $transitionNames);
        }

        $toPlace = $this->stateMachineRegistry->transition(Defaults::ORDER_STATE_MACHINE, $order->getStateMachineState()->getTechnicalName(), $transition, $context);

        $payload = [
            ['id' => $order->getId(), 'stateId' => $toPlace->getId()],
        ];

        $this->orderRepository->update($payload, $context);

        $order->setStateMachineState($toPlace);
        $order->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($order, OrderDefinition::class, $request, $context);
    }

    private function getOrder(string $id, Context $context): OrderEntity
    {
        $result = $this->orderRepository->search(new Criteria([$id]), $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException(OrderDefinition::getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
