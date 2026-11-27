<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CartContextHashStruct extends Struct
{
    protected ?float $price = null;

    protected ?string $shippingMethod = null;

    protected ?string $paymentMethod = null;

    /**
     * @var array<string, mixed>
     */
    protected array $lineItems;

    /**
     * @var list<array{shippingMethod: string, address: array{id: string, hash: string|null}|null, positions: array<string, int>}>
     */
    protected array $deliveries = [];

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): void
    {
        $this->price = $price;
    }

    public function getShippingMethod(): ?string
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(?string $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * @param array<string, mixed> $lineItems
     */
    public function setLineItems(array $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    /**
     * @param array<string, mixed> $lineItem
     */
    public function addLineItem(string $id, array $lineItem): void
    {
        $this->lineItems[$id] = $lineItem;
    }

    /**
     * @return list<array{shippingMethod: string, address: array{id: string, hash: string|null}|null, positions: array<string, int>}>
     */
    public function getDeliveries(): array
    {
        return $this->deliveries;
    }

    /**
     * @param list<array{shippingMethod: string, address: array{id: string, hash: string|null}|null, positions: array<string, int>}> $deliveries
     */
    public function setDeliveries(array $deliveries): void
    {
        $this->deliveries = $deliveries;
    }

    /**
     * @param array{shippingMethod: string, address: array{id: string, hash: string|null}|null, positions: array<string, int>} $delivery
     */
    public function addDelivery(array $delivery): void
    {
        $this->deliveries[] = $delivery;
    }
}
