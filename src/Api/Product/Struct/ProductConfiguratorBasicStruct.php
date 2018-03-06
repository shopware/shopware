<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionBasicStruct;
use Shopware\Api\Entity\Entity;

class ProductConfiguratorBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $configurationOptionId;

    /**
     * @var string
     */
    protected $productVersionId;

    /**
     * @var string
     */
    protected $configurationOptionVersionId;

    /**
     * @var array|null
     */
    protected $price;

    /**
     * @var array|null
     */
    protected $prices;

    /**
     * @var ConfigurationGroupOptionBasicStruct
     */
    protected $configurationOption;

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function setVersionId(string $versionId): void
    {
        $this->versionId = $versionId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getConfigurationOptionId(): string
    {
        return $this->configurationOptionId;
    }

    public function setConfigurationOptionId(string $configurationOptionId): void
    {
        $this->configurationOptionId = $configurationOptionId;
    }

    public function getProductVersionId(): string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getConfigurationOptionVersionId(): string
    {
        return $this->configurationOptionVersionId;
    }

    public function setConfigurationOptionVersionId(string $configurationOptionVersionId): void
    {
        $this->configurationOptionVersionId = $configurationOptionVersionId;
    }

    public function getPrice(): ?array
    {
        return $this->price;
    }

    public function setPrice(?array $price): void
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

    public function getConfigurationOption(): ConfigurationGroupOptionBasicStruct
    {
        return $this->configurationOption;
    }

    public function setConfigurationOption(ConfigurationGroupOptionBasicStruct $configurationOption): void
    {
        $this->configurationOption = $configurationOption;
    }
}
