<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;

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
     * This message is not used on successful outcomes.
     * The message should be provided on failure.
     * Payment will fail if provided.
     */
    protected ?string $message = null;

    /**
     * This is the URL the user is redirected to after the app has received the order data.
     */
    protected ?string $redirectUrl = null;

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function validate(string $transactionId): void
    {
    }
}
