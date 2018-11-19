<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStruct;
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

class OrderTransactionActionController extends AbstractController
{
    /**
     * @var RepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(RepositoryInterface $orderTransactionRepository, StateMachineRegistry $stateMachineRegistry)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/order-transaction/{transactionId}/actions/state", name="api.order_transaction.get_state", methods={"GET"})
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

        /** @var StateMachineStateStruct $place */
        foreach ($stateMachine->getStates()->getElements() as $place) {
            if ($place->getTechnicalName() === $transaction->getState()->getTechnicalName()) {
                $currentState = [
                    'name' => $place->getName(),
                    'technicalName' => $place->getTechnicalName(),
                ];
            }
        }

        /** @var StateMachineTransitionStruct $transition */
        foreach ($stateMachine->getTransitions()->getElements() as $transition) {
            if ($transition->getFromState()->getTechnicalName() !== $transaction->getState()->getTechnicalName()) {
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
     * @Route("/api/v{version}/order-transaction/{transactionId}/actions/state/{transition?}", name="api.order_transaction.transition_state", methods={"POST"})
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
            $transitions = $this->stateMachineRegistry->getAvailableTransitions(Defaults::ORDER_STATE_MACHINE, $transaction->getState()->getTechnicalName(), $context);
            $transitionNames = array_map(function (StateMachineTransitionStruct $transition) {
                return $transition->getActionName();
            }, $transitions);

            throw new IllegalTransitionException($transaction->getState()->getName(), '', $transitionNames);
        }

        $toPlace = $this->stateMachineRegistry->transition(Defaults::ORDER_TRANSACTION_STATE_MACHINE, $transaction->getState()->getTechnicalName(), $transition, $context);

        $payload = [
            ['id' => $transaction->getId(), 'stateId' => $toPlace->getId()],
        ];

        $this->orderTransactionRepository->update($payload, $context);

        $transaction->setState($toPlace);
        $transaction->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($transaction, OrderTransactionDefinition::class, $request, $context);
    }

    private function getOrderTransaction(string $id, Context $context): OrderTransactionStruct
    {
        $result = $this->orderTransactionRepository->read(new ReadCriteria([$id]), $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException(OrderTransactionDefinition::getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
