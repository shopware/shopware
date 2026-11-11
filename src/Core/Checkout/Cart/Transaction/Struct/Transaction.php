<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Transaction extends Struct
{
    protected ?Struct $validationStruct = null;

    /**
     * @var array<string, scalar|array<array-key, mixed>|null>
     */
    protected array $payload = [];

    /**
     * @var array<string, bool>
     */
    private array $payloadProtection = [];

    public function __construct(
        protected CalculatedPrice $amount,
        protected string $paymentMethodId
    ) {
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getValidationStruct(): ?Struct
    {
        return $this->validationStruct;
    }

    public function setValidationStruct(?Struct $validationStruct): void
    {
        $this->validationStruct = $validationStruct;
    }

    /**
     * @return array<string, scalar|array<array-key, mixed>|null>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, scalar|array<array-key, mixed>|null> $payload
     * @param array<string, bool> $protection
     */
    public function setPayload(array $payload, array $protection = []): void
    {
        $this->payload = $payload;
        $this->addPayloadProtection($protection);
    }

    public function getPayloadValue(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }

    public function hasPayloadValue(string $key): bool
    {
        return isset($this->payload[$key]);
    }

    /**
     * @param scalar|array<array-key, mixed>|null $value
     */
    public function setPayloadValue(string $key, string|int|float|bool|array|null $value, ?bool $protected = null): void
    {
        $this->payload[$key] = $value;

        if ($protected !== null) {
            $this->addPayloadProtection([$key => $protected]);
        }
    }

    public function removePayloadValue(string $key): void
    {
        unset($this->payload[$key], $this->payloadProtection[$key]);
    }

    /**
     * @param array<string, bool> $protection
     */
    public function addPayloadProtection(array $protection): void
    {
        $this->payloadProtection = \array_replace($this->payloadProtection, $protection);
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['payload'] = \array_diff_key($this->payload, \array_filter($this->payloadProtection));

        return $data;
    }

    public function getApiAlias(): string
    {
        return 'cart_transaction';
    }
}
