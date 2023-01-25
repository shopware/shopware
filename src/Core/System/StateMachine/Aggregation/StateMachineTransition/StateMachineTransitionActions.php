<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final class StateMachineTransitionActions
{
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_COMPLETE = 'complete';
    public const ACTION_DO_PAY = 'do_pay';
    public const ACTION_FAIL = 'fail';
    public const ACTION_PAID = 'paid';
    public const ACTION_PAID_PARTIALLY = 'paid_partially';
    public const ACTION_PROCESS = 'process';
    public const ACTION_PROCESS_UNCONFIRMED = 'process_unconfirmed';
    public const ACTION_REFUND = 'refund';
    public const ACTION_REFUND_PARTIALLY = 'refund_partially';
    public const ACTION_REMIND = 'remind';
    public const ACTION_REOPEN = 'reopen';
    public const ACTION_RETOUR = 'retour';
    public const ACTION_RETOUR_PARTIALLY = 'retour_partially';
    public const ACTION_SHIP = 'ship';
    public const ACTION_SHIP_PARTIALLY = 'ship_partially';
    public const ACTION_AUTHORIZE = 'authorize';
    public const ACTION_CHARGEBACK = 'chargeback';
}
