<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class TokenStruct extends Struct
{
    protected ?string $id;

    protected ?string $token;

    protected ?string $paymentMethodId;

    protected ?string $transactionId;

    protected ?string $finishUrl;

    protected ?string $errorUrl;

    protected ?\Throwable $exception;

    protected int $expires;

    public function __construct(
        ?string $id = null,
        ?string $token = null,
        ?string $paymentMethodId = null,
        ?string $transactionId = null,
        ?string $finishUrl = null,
        ?int $expires = null,
        ?string $errorUrl = null
    ) {
        $this->id = $id;
        $this->token = $token;
        $this->paymentMethodId = $paymentMethodId;
        $this->transactionId = $transactionId;
        $this->finishUrl = $finishUrl;
        $this->expires = $expires ?? 1800;
        $this->errorUrl = $errorUrl;
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
