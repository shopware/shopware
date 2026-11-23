<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
trait PayloadTrait
{
    /**
     * @var array<string, scalar|array<array-key, mixed>|null>
     */
    protected array $payload = [];

    /**
     * @var array<string, bool>
     */
    private array $payloadProtection = [];

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
    public function setPayload(array $payload, array $protection = []): self
    {
        foreach ($payload as $key => $value) {
            $this->setPayloadValue($key, $value);
        }

        return $this->addPayloadProtection($protection);
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
    public function setPayloadValue(string $key, string|int|float|bool|array|null $value, ?bool $protected = null): self
    {
        $this->payload[$key] = $value;

        if ($protected !== null) {
            $this->addPayloadProtection([$key => $protected]);
        }

        return $this;
    }

    /**
     * @throws CartException
     */
    public function removePayloadValue(string $key): void
    {
        if (!$this->hasPayloadValue($key)) {
            throw CartException::structPayloadKeyNotFound($key, $this->getApiAlias());
        }

        unset($this->payload[$key], $this->payloadProtection[$key]);
    }

    /**
     * @param array<string, bool> $protection
     */
    public function addPayloadProtection(array $protection): self
    {
        $this->payloadProtection = \array_replace($this->payloadProtection, $protection);

        return $this;
    }

    /**
     * @return array<string, scalar|array<array-key, mixed>|null>
     */
    private function getUnprotectedPayload(): array
    {
        return \array_diff_key($this->payload, \array_filter($this->payloadProtection));
    }
}
