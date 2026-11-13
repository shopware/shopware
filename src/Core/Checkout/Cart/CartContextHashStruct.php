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
     * @var array<string, mixed>|null
     */
    protected ?array $billingAddress = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $shippingAddress = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $customer = null;

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
     * @return array<string, mixed>|null
     */
    public function getBillingAddress(): ?array
    {
        return $this->billingAddress;
    }

    /**
     * @param array<string, mixed>|null $billingAddress
     */
    public function setBillingAddress(?array $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getShippingAddress(): ?array
    {
        return $this->shippingAddress;
    }

    /**
     * @param array<string, mixed>|null $shippingAddress
     */
    public function setShippingAddress(?array $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomer(): ?array
    {
        return $this->customer;
    }

    /**
     * @param array<string, mixed>|null $customer
     */
    public function setCustomer(?array $customer): void
    {
        $this->customer = $customer;
    }
}
