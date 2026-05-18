<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Clock\NativeClock;

/**
 * @deprecated tag:v6.8.0 - will be removed, use `PaymentToken` instead
 */
#[Package('checkout')]
class TokenStruct extends Struct
{
    protected ?\Throwable $exception = null;

    protected int $expires;

    protected readonly ClockInterface $clock;

    // @TODO clock-bc: review public ctor change for BC
    public function __construct(
        protected ?string $id = null,
        protected ?string $token = null,
        protected ?string $paymentMethodId = null,
        protected ?string $transactionId = null,
        protected ?string $finishUrl = null,
        ?int $expires = null,
        protected ?string $errorUrl = null,
        ?ClockInterface $clock = null,
    ) {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        $this->expires = $expires ?? 1800;
        $this->clock = $clock ?? new NativeClock();
    }

    public function getId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->id;
    }

    public function getToken(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->token;
    }

    public function getPaymentMethodId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->paymentMethodId;
    }

    public function getTransactionId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->transactionId;
    }

    public function getFinishUrl(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->finishUrl;
    }

    public function getErrorUrl(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->errorUrl;
    }

    public function setErrorUrl(?string $errorUrl): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        $this->errorUrl = $errorUrl;
    }

    public function getExpires(): int
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->expires;
    }

    public function isExpired(): bool
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->expires < $this->clock->now()->getTimestamp();
    }

    public function getException(): ?\Throwable
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return $this->exception;
    }

    public function setException(?\Throwable $exception): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        $this->exception = $exception;
    }

    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(static::class, 'v6.8.0.0', PaymentToken::class));

        return 'payment_token';
    }
}
