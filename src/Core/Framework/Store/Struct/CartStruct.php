<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class CartStruct extends Struct
{
    protected float $netPrice;

    protected float $taxValue;

    protected float $taxRate;

    protected float $grossPrice;

    protected CartPositionCollection $positions;

    /**
     * @var array<string, mixed>
     */
    protected array $shop;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): CartStruct
    {
        $data['positions'] = new CartPositionCollection($data['positions']);

        return (new self())->assign($data);
    }

    public function getNetPrice(): float
    {
        return $this->netPrice;
    }

    public function setNetPrice(float $netPrice): void
    {
        $this->netPrice = $netPrice;
    }

    public function getTaxValue(): float
    {
        return $this->taxValue;
    }

    public function setTaxValue(float $taxValue): void
    {
        $this->taxValue = $taxValue;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getGrossPrice(): float
    {
        return $this->grossPrice;
    }

    public function setGrossPrice(float $grossPrice): void
    {
        $this->grossPrice = $grossPrice;
    }

    public function getPositions(): CartPositionCollection
    {
        return $this->positions;
    }

    public function setPositions(CartPositionCollection $positions): void
    {
        $this->positions = $positions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getShop(): array
    {
        return $this->shop;
    }

    /**
     * @param array<string, mixed> $shop
     */
    public function setShop(array $shop): void
    {
        $this->shop = $shop;
    }

    public function getShopId(): int
    {
        return $this->getShop()['id'];
    }

    public function getShopDomain(): string
    {
        return $this->getShop()['domain'];
    }

    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        unset($vars['extensions']);

        return $vars;
    }
}
