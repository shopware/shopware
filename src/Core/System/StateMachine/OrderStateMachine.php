<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

final class OrderStateMachine
{
    public const NAME = 'order.state';
    public const STATE_OPEN = 'open';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_COMPLETED = 'completed';
    public const STATE_CANCELLED = 'cancelled';
}
