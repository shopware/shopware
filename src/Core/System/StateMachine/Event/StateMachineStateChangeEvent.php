<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class StateMachineStateChangeEvent extends Event
{
    final public const STATE_MACHINE_TRANSITION_SIDE_ENTER = 'state_enter';
    final public const STATE_MACHINE_TRANSITION_SIDE_LEAVE = 'state_leave';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var StateMachineEntity
     */
    protected $stateMachine;

    /**
     * @var string
     */
    protected $transitionSide;

    /**
     * @var string
     */
    protected $stateName;

    /**
     * @var StateMachineStateEntity
     */
    protected $previousState;

    /**
     * @var StateMachineStateEntity
     */
    protected $nextState;

    /**
     * @var Transition
     */
    protected $transition;

    public function __construct(
        Context $context,
        string $transitionSide,
        Transition $transition,
        StateMachineEntity $stateMachine,
        StateMachineStateEntity $previousState,
        StateMachineStateEntity $nextState,
        private readonly ?MailRecipientStruct $mailRecipientStruct = null
    ) {
        $this->context = $context;
        $this->stateMachine = $stateMachine;
        $this->transitionSide = $transitionSide;
        $this->previousState = $previousState;
        $this->nextState = $nextState;
        $this->transition = $transition;

        if ($this->transitionSide === static::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            $this->stateName = $this->nextState->getTechnicalName();
        } else {
            $this->stateName = $this->previousState->getTechnicalName();
        }
    }

    public function getName(): string
    {
        return 'state_machine.' . $this->stateMachine->getTechnicalName() . '_changed';
    }

    public function getStateEventName(): string
    {
        return $this->transitionSide . '.' . $this->stateMachine->getTechnicalName() . '.' . $this->stateName;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTransition(): Transition
    {
        return $this->transition;
    }

    public function getNextState(): StateMachineStateEntity
    {
        return $this->nextState;
    }

    public function getPreviousState(): StateMachineStateEntity
    {
        return $this->previousState;
    }

    public function getStateName(): string
    {
        return $this->stateName;
    }

    public function getTransitionSide(): string
    {
        return $this->transitionSide;
    }

    public function getStateMachine(): StateMachineEntity
    {
        return $this->stateMachine;
    }

    public function getMailRecipientStruct(): ?MailRecipientStruct
    {
        return $this->mailRecipientStruct;
    }
}
