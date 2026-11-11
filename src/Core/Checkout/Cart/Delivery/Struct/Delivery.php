<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Delivery extends Struct
{
    /**
     * @var array<string, scalar|array<array-key, mixed>|null>
     */
    protected array $payload = [];

    /**
     * @var array<string, bool>
     */
    private array $payloadProtection = [];

    public function __construct(
        protected DeliveryPositionCollection $positions,
        protected DeliveryDate $deliveryDate,
        protected ShippingMethodEntity $shippingMethod,
        protected ShippingLocation $location,
        protected CalculatedPrice $shippingCosts
    ) {
    }

    public function getPositions(): DeliveryPositionCollection
    {
        return $this->positions;
    }

    public function getLocation(): ShippingLocation
    {
        return $this->location;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getShippingCosts(): CalculatedPrice
    {
        return $this->shippingCosts;
    }

    public function setShippingCosts(CalculatedPrice $shippingCosts): void
    {
        $this->shippingCosts = $shippingCosts;
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
        return 'cart_delivery';
    }
}
