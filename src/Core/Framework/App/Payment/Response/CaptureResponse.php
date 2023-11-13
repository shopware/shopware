<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class CaptureResponse extends AbstractResponse
{
    /**
     * One of the possible transaction statusses based on status set in pay-response.
     * Usually, this is one of: paid, paid_partially, authorize, process, fail
     */
    protected string $status = StateMachineTransitionActions::ACTION_PAID;

    /**
     * This message is not used on successful outcomes.
     * The message should be provided on failure.
     * Payment will fail if provided.
     */
    protected ?string $message = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function validate(string $transactionId): void
    {
        // no status & message = open
        // message = fail
        // status = status
        // message & status = fail
    }
}
