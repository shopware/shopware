<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

/**
 * @deprecated tag:v6.7.0 - will be removed
 *
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class CaptureResponse extends AbstractResponse
{
    /**
     * One of the possible transaction statusses based on status set in pay-response.
     * Usually, this is one of: paid, paid_partially, authorize, process, fail
     */
    protected string $status = StateMachineTransitionActions::ACTION_PAID;

    public function getStatus(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'Payment flow `capture` will be removed'
        );

        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'Payment flow `capture` will be removed'
        );

        if (parent::getErrorMessage()) {
            return parent::getErrorMessage();
        }

        if ($this->status === StateMachineTransitionActions::ACTION_FAIL) {
            return 'Payment was reported as failed.';
        }

        return null;
    }
}
