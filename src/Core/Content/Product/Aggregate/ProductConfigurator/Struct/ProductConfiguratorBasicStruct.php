<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Struct;

use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\ORM\Entity;

class ProductConfiguratorBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $optionId;

    /**
     * @var PriceStruct|null
     */
    protected $price;

    /**
     * @var array|null
     */
    protected $prices;

    /**
     * @var \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct
     */
    protected $option;

    /**
     * @var bool
     */
    protected $selected = false;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function setOptionId(string $optionId): void
    {
        $this->optionId = $optionId;
    }

    public function getPrice(): ?PriceStruct
    {
        return $this->price;
    }

    public function setPrice(?\Shopware\Core\Framework\Pricing\PriceStruct $price): void
    {
        $this->price = $price;
    }

    public function getPrices(): ?array
    {
        return $this->prices;
    }

    public function setPrices(?array $prices): void
    {
        $this->prices = $prices;
    }

    public function getOption(): \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct
    {
        return $this->option;
    }

    public function setOption(
        \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct $option): void
    {
        $this->option = $option;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function setSelected(bool $selected): void
    {
        $this->selected = $selected;
    }
}
