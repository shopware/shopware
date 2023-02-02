<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ValidateResponse extends AbstractResponse
{
    /**
     * This message is not used on successful outcomes.
     * The message should be provided on failure.
     * Payment will fail if provided.
     */
    protected ?string $message = null;

    /**
     * This will be sent with the capture call for the app to identify the verified payment
     */
    protected array $preOrderPayment = [];

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getPreOrderPayment(): array
    {
        return $this->preOrderPayment;
    }

    public function validate(string $transactionId): void
    {
    }
}
