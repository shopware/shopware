<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\Exception\StateMachineStateNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class OrderTransactionStateHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    public function __construct(
        EntityRepositoryInterface $orderTransactionRepository,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function open(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_OPEN,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function pay(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_PAID,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function payPartially(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function refund(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_REFUNDED,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function refundPartially(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function cancel(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_CANCELLED,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function remind(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_REMINDED,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    private function writeNewState(string $transactionId, string $stateId, Context $context): void
    {
        $data = [
            'id' => $transactionId,
            'stateId' => $stateId,
        ];

        $this->orderTransactionRepository->update([$data], $context);
    }
}
