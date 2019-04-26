<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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

    public function cancel(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_CANCELLED,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    public function complete(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_PAID,
            $context
        )->getId();

        $this->writeNewState($transactionId, $stateId, $context);
    }

    public function open(string $transactionId, Context $context): void
    {
        $stateId = $this->stateMachineRegistry->getStateByTechnicalName(
            OrderTransactionStates::STATE_MACHINE,
            OrderTransactionStates::STATE_OPEN,
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
