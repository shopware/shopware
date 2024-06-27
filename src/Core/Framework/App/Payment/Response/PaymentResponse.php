<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentResponse extends AbstractResponse
{
    /**
     * One of the possible transaction statuses based on status set in pay-response.
     * Usually, this is one of: paid, paid_partially, authorize, process, fail
     * By default, the payment will remain on 'open'
     */
    protected ?string $status = null;

    /**
     * This is the URL the user is redirected to after the app has received the order data.
     */
    protected ?string $redirectUrl = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function getErrorMessage(): ?string
    {
        if (parent::getErrorMessage()) {
            return parent::getErrorMessage();
        }

        if ($this->status === StateMachineTransitionActions::ACTION_FAIL) {
            return 'Payment was reported as failed.';
        }

        return null;
    }
}
