<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class RefundResponse extends AbstractResponse
{
    /**
     * One of the possible transaction statuses based on status set in refund-response.
     * Usually, this is one of: complete, fail, reopen
     * By default, the refund will remain on 'open'
     *
     * @See StateMachineTransitionActions
     */
    protected ?string $status = null;

    /**
     * This message is not used on successful outcomes.
     * The message should be provided on failure.
     * Refund will fail if provided.
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
    }
}
