<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class CartContextHashStruct extends Struct
{
    protected ?float $price;

    protected ?string $shippingMethod;

    protected ?string $paymentMethod;

    /**
     * @var array<string, mixed>
     */
    protected array $lineItems;

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
     * @return  array<string, mixed>
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
}
