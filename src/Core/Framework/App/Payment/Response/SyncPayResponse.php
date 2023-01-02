<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class SyncPayResponse extends AbstractResponse
{
    /**
     * One of the possible transaction statuses based on status set in pay-response.
     * Usually, this is one of: paid, paid_partially, authorize, process, fail
     * By default, the payment will remain on 'open'
     */
    protected ?string $status = null;

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
