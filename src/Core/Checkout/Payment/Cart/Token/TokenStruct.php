<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class TokenStruct extends Struct
{
    protected ?\Throwable $exception = null;

    protected int $expires;

    public function __construct(
        protected ?string $id = null,
        protected ?string $token = null,
        protected ?string $paymentMethodId = null,
        protected ?string $transactionId = null,
        protected ?string $finishUrl = null,
        ?int $expires = null,
        protected ?string $errorUrl = null
    ) {
        $this->expires = $expires ?? 1800;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->paymentMethodId;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getFinishUrl(): ?string
    {
        return $this->finishUrl;
    }

    public function getErrorUrl(): ?string
    {
        return $this->errorUrl;
    }

    public function setErrorUrl(?string $errorUrl): void
    {
        $this->errorUrl = $errorUrl;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function isExpired(): bool
    {
        return $this->expires < time();
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    public function setException(?\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    public function getApiAlias(): string
    {
        return 'payment_token';
    }
}
