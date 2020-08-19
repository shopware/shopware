<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Checkout\Order\Exception\OrderTransactionCaptureNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class OrderTransactionCaptureStateHandler
{
    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionCaptureRepository;

    public function __construct(
        StateMachineRegistry $stateMachineRegistry,
        EntityRepositoryInterface $orderTransactionCaptureRepository
    ) {
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderTransactionCaptureRepository = $orderTransactionCaptureRepository;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws IllegalTransitionException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     */
    public function complete(string $transactionCaptureId, Context $context): void
    {
        $orderTransactionCapture = $this->fetchOrderTransactionCapture($transactionCaptureId, $context);
        if ($orderTransactionCapture->getStateMachineState()->getTechnicalName() === OrderTransactionCaptureStates::STATE_COMPLETED) {
            return;
        }
        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionCaptureDefinition::ENTITY_NAME,
                $transactionCaptureId,
                StateMachineTransitionActions::ACTION_COMPLETE,
                'stateId'
            ),
            $context
        );
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws IllegalTransitionException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     */
    public function fail(string $transactionCaptureId, Context $context): void
    {
        $orderTransactionCapture = $this->fetchOrderTransactionCapture($transactionCaptureId, $context);
        if ($orderTransactionCapture->getStateMachineState()->getTechnicalName() === OrderTransactionCaptureStates::STATE_FAILED) {
            return;
        }
        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionCaptureDefinition::ENTITY_NAME,
                $transactionCaptureId,
                StateMachineTransitionActions::ACTION_FAIL,
                'stateId'
            ),
            $context
        );
    }

    private function fetchOrderTransactionCapture(
        string $orderTransactionCaptureId,
        Context $context
    ): OrderTransactionCaptureEntity {
        $criteria = new Criteria([$orderTransactionCaptureId]);
        $criteria->addAssociation('state');

        $orderTransactionCapture = $this->orderTransactionCaptureRepository->search($criteria, $context)->first();
        if ($orderTransactionCapture === null) {
            throw new OrderTransactionCaptureNotFoundException($orderTransactionCaptureId);
        }

        return $orderTransactionCapture;
    }
}
