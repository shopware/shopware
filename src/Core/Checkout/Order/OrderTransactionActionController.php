<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
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

class OrderTransactionActionController extends AbstractController
{
    /**
     * @var EntityRepository
     */
    private $orderTransactionRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(EntityRepository $orderTransactionRepository, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/_action/order-transaction/{transactionId}/state", name="api.order_transaction.get_state", methods={"GET"})
     */
    public function getAvailableTransitions(Request $request, Context $context, string $transactionId): Response
    {
        $transaction = $this->getOrderTransaction($transactionId, $context);
        $stateMachine = $this->stateMachineRegistry->getStateMachine(Defaults::ORDER_TRANSACTION_STATE_MACHINE, $context);

        $transitions = [];
        $baseUrl = $this->generateUrl('api.order_transaction.transition_state', [
            'transactionId' => $transaction->getId(),
            'version' => $request->get('version'),
        ]);

        $currentState = null;

        /** @var StateMachineStateEntity $place */
        foreach ($stateMachine->getStates()->getElements() as $place) {
            if ($place->getTechnicalName() === $transaction->getStateMachineState()->getTechnicalName()) {
                $currentState = [
                    'name' => $place->getName(),
                    'technicalName' => $place->getTechnicalName(),
                ];
            }
        }

        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachine->getTransitions()->getElements() as $transition) {
            if ($transition->getFromStateMachineState()->getTechnicalName() !== $transaction->getStateMachineState()->getTechnicalName()) {
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
     * @Route("/api/v{version}/_action/order-transaction/{transactionId}/state/{transition?}", name="api.order_transaction.transition_state", methods={"POST"})
     */
    public function transitionOrderState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $transactionId,
        string $transition = null
    ): Response {
        $transaction = $this->getOrderTransaction($transactionId, $context);

        if (empty($transition)) {
            $transitions = $this->stateMachineRegistry->getAvailableTransitions(Defaults::ORDER_STATE_MACHINE, $transaction->getStateMachineState()->getTechnicalName(), $context);
            $transitionNames = array_map(function (StateMachineTransitionEntity $transition) {
                return $transition->getActionName();
            }, $transitions);

            throw new IllegalTransitionException($transaction->getStateMachineState()->getName(), '', $transitionNames);
        }

        $toPlace = $this->stateMachineRegistry->transition(Defaults::ORDER_TRANSACTION_STATE_MACHINE, $transaction->getStateMachineState()->getTechnicalName(), $transition, $context);

        $payload = [
            ['id' => $transaction->getId(), 'stateId' => $toPlace->getId()],
        ];

        $this->orderTransactionRepository->update($payload, $context);

        $transaction->setStateMachineState($toPlace);
        $transaction->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($transaction, OrderTransactionDefinition::class, $request, $context);
    }

    private function getOrderTransaction(string $id, Context $context): OrderTransactionEntity
    {
        $result = $this->orderTransactionRepository->search(new Criteria([$id]), $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException(OrderTransactionDefinition::getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
