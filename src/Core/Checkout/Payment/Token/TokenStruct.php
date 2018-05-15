<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Token;

use Shopware\Framework\Struct\Struct;

class TokenStruct extends Struct
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $paymentMethodId;

    /**
     * @var string
     */
    private $transactionId;

    /**
     * @var \DateTime
     */
    private $expires;

    public function __construct(
        string $id,
        string $token,
        string $paymentMethodId,
        string $transactionId,
        \DateTime $expires
    ) {
        $this->id = $id;
        $this->token = $token;
        $this->paymentMethodId = $paymentMethodId;
        $this->transactionId = $transactionId;
        $this->expires = $expires;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @return string
     */
    public function getExpires(): \DateTime
    {
        return $this->expires;
    }

    public function isExpired(): bool
    {
        return $this->expires < new \DateTime();
    }
}
