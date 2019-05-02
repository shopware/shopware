<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Api;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Api\Exception\ResourceNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderTransactionActionController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $orderTransactionRepository;

    /**
     * @var StateMachineRegistry
     */
    protected $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderTransactionRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @Route("/api/v{version}/_action/order-transaction/{transactionId}/state", name="api.order_transaction.get_state", methods={"GET"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws ResourceNotFoundException
     * @throws StateMachineNotFoundException
     */
    public function getAvailableTransitions(Request $request, Context $context, string $transactionId): Response
    {
        $transaction = $this->getOrderTransaction($transactionId, $context);

        $baseUrl = $this->generateUrl('api.order_transaction.transition_state', [
            'transactionId' => $transaction->getId(),
            'version' => $request->get('version'),
        ]);

        return $this->stateMachineRegistry->buildAvailableTransitionsJsonResponse(OrderTransactionStates::STATE_MACHINE,
            $transaction->getStateMachineState()->getTechnicalName(),
            $baseUrl,
            $context);
    }

    /**
     * @Route("/api/v{version}/_action/order-transaction/{transactionId}/state/{transition?}", name="api.order_transaction.transition_state", methods={"POST"})
     *
     * @throws InconsistentCriteriaIdsException
     * @throws ResourceNotFoundException
     * @throws IllegalTransitionException
     * @throws StateMachineNotFoundException
     */
    public function transitionOrderState(
        Request $request,
        Context $context,
        ResponseFactoryInterface $responseFactory,
        string $transactionId,
        ?string $transition = null
    ): Response {
        $transaction = $this->getOrderTransaction($transactionId, $context);

        $toPlace = $this->stateMachineRegistry->transition($this->stateMachineRegistry->getStateMachine(OrderTransactionStates::STATE_MACHINE, $context),
            $transaction->getStateMachineState(),
            $this->orderTransactionRepository->getDefinition()->getEntityName(),
            $transaction->getId(),
            $context,
            $transition);

        $payload = [
            ['id' => $transaction->getId(), 'stateId' => $toPlace->getId()],
        ];

        $this->orderTransactionRepository->update($payload, $context);
        $transaction->setStateMachineState($toPlace);
        $transaction->setStateId($toPlace->getId());

        return $responseFactory->createDetailResponse($transaction, $this->orderTransactionRepository->getDefinition(), $request, $context);
    }

    /**
     * @throws ResourceNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderTransaction(string $id, Context $context): OrderTransactionEntity
    {
        $result = $this->orderTransactionRepository->search(new Criteria([$id]), $context);

        if ($result->count() === 0) {
            throw new ResourceNotFoundException($this->orderTransactionRepository->getDefinition()->getEntityName(), ['id' => $id]);
        }

        return $result->first();
    }
}
