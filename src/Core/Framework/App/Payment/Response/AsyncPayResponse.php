<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal only for use by the app-system
 */
class AsyncPayResponse extends AbstractResponse
{
    /**
     * One of the possible transaction statuses based on status set in pay-response.
     * Usually, this is one of: do_pay, remind, fail
     *
     * By default, 'do_pay' is used
     *
     * @deprecated tag:v6.5.0 - default will be StateMachineTransitionActions::ACTION_PROCESS_UNCONFIRMED
     */
    protected string $status = StateMachineTransitionActions::ACTION_DO_PAY;

    /**
     * This message is not used on successful outcomes.
     * The message should be provided on failure.
     * Payment will fail if provided.
     */
    protected ?string $message = null;

    /**
     * This is the URL the user is redirected to after the app has received the order data.
     */
    protected string $redirectUrl = '';

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function validate(string $transactionId): void
    {
        if (!$this->redirectUrl
            && !$this->message
            && $this->status !== StateMachineTransitionActions::ACTION_FAIL
        ) {
            throw new AsyncPaymentProcessException($transactionId, 'No redirect URL provided by App');
        }

        if (Feature::isActive('FEATURE_NEXT_13601') && $this->getStatus() === StateMachineTransitionActions::ACTION_DO_PAY) {
            $this->status = StateMachineTransitionActions::ACTION_PROCESS_UNCONFIRMED;
        }
    }
}
