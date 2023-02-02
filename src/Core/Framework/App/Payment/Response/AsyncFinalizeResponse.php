<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal only for use by the app-system
 */
class AsyncFinalizeResponse extends AbstractResponse
{
    /**
     * One of the possible transaction statusses based on status set in pay-response.
     * Usually, this is one of: paid, paid_partially, authorize, cancel, fail
     *
     * If user aborted payment, the desired state is cancel.
     */
    protected string $status = StateMachineTransitionActions::ACTION_PAID;

    /**
     * This message is not used on successful outcomes. It is used, if status is
     * - cancelled: optional
     * - failed: required
     */
    protected ?string $message = null;

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function validate(string $transactionId): void
    {
        // status must be not null
        // if message is set: fail
    }
}
