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
     * @see StateMachineTransitionActions
     */
    protected ?string $status = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        if (parent::getErrorMessage()) {
            return parent::getErrorMessage();
        }

        if ($this->status === StateMachineTransitionActions::ACTION_FAIL) {
            return 'Refund was reported as failed.';
        }

        return null;
    }
}
