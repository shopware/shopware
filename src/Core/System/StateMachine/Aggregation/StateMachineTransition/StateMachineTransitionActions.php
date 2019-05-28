<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition;

final class StateMachineTransitionActions
{
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_COMPLETE = 'complete';
    public const ACTION_PAY = 'pay';
    public const ACTION_PAY_PARTIALLY = 'pay_partially';
    public const ACTION_PROCESS = 'process';
    public const ACTION_REFUND = 'refund';
    public const ACTION_REFUND_PARTIALLY = 'refund_partially';
    public const ACTION_REMIND = 'remind';
    public const ACTION_REOPEN = 'reopen';
    public const ACTION_RETOUR = 'retour';
    public const ACTION_RETOUR_PARTIALLY = 'retour_partially';
    public const ACTION_SHIP = 'ship';
    public const ACTION_SHIP_PARTIALLY = 'ship_partially';
}
