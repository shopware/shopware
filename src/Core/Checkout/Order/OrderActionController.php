<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

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

class OrderActionController extends AbstractController
{
    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(RepositoryInterface $orderRepository, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderRepository = $orderRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/order/{orderId}/actions/state", name="api.order.get_state", methods={"GET"})
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

        /** @var StateMachineStateStruct $place */
        foreach ($stateMachine->getStates()->getElements() as $place) {
            if ($place->getTechnicalName() === $order->getState()->getTechnicalName()) {
                $currentState = [
                    'name' => $place->getName(),
                    'technicalName' => $place->getTechnicalName(),
                ];
            }
        }

        /** @var StateMachineTransitionStruct $transition */
        foreach ($stateMachine->getTransitions()->getElements() as $transition) {
            if ($transition->getFromState()->getTechnicalName() !== $order->getState()->getTechnicalName()) {
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
     * @Route("/api/v{version}/order/{orderId}/actions/state/{transition?}", name="api.order.transition_state", methods={"POST"})
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
            $transitions = $this->stateMachineRegistry->getAvailableTransitions(Defaults::ORDER_STATE_MACHINE, $order->getState()->getTechnicalName(), $context);
            $transitionNames = array_map(function (StateMachineTransitionStruct $transition) {
                return $transition->getActionName();
            }, $transitions);

            throw new IllegalTransitionException($order->getState()->getName(), '', $transitionNames);
        }

        $toPlace = $this->stateMachineRegistry->transition(Defaults::ORDER_STATE_MACHINE, $order->getState()->getTechnicalName(), $transition, $context);

        $payload = [
            ['id' => $order->getId(), 'stateId' => $toPlace->getId()],
        ];

        $this->orderRepository->update($payload, $context);

        $order->setState($toPlace);
        $order->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($order, OrderDefinition::class, $request, $context);
    }

    private function getOrder(string $id, Context $context): OrderStruct
    {
        $result = $this->orderRepository->read(new ReadCriteria([$id]), $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException(OrderDefinition::getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
