<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Framework\Struct\Struct;

class TokenStruct extends Struct
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $transactionId;

    /**
     * @var string|null
     */
    protected $finishUrl;

    /**
     * @var int Unix Timestamp
     */
    protected $expires;

    public function __construct(
        string $id,
        string $token,
        string $paymentMethodId,
        string $transactionId,
        ?string $finishUrl,
        int $expires
    ) {
        $this->id = $id;
        $this->token = $token;
        $this->paymentMethodId = $paymentMethodId;
        $this->transactionId = $transactionId;
        $this->finishUrl = $finishUrl;
        $this->expires = $expires;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getFinishUrl(): ?string
    {
        return $this->finishUrl;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function isExpired(): bool
    {
        return $this->expires < time();
    }
}
