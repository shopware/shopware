<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

#[Package('customer-order')]
class OrderTransactionCaptureStateHandler
{
    /**
     * @internal
     */
    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
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

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws IllegalTransitionException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     */
    public function reopen(string $transactionCaptureId, Context $context): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionCaptureDefinition::ENTITY_NAME,
                $transactionCaptureId,
                StateMachineTransitionActions::ACTION_REOPEN,
                'stateId'
            ),
            $context
        );
    }
}
