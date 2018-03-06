<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductConfiguratorBasicStruct;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;

class ProductConfiguratorBasicCollection extends EntityCollection
{
    /**
     * @var ProductConfiguratorBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductConfiguratorBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductConfiguratorBasicStruct
    {
        return parent::current();
    }


    public function getProductIds(): array
    {
        return $this->fmap(function(ProductConfiguratorBasicStruct $productConfigurator) {
            return $productConfigurator->getProductId();
        });
    }

    public function filterByProductId(string $id): ProductConfiguratorBasicCollection
    {
        return $this->filter(function(ProductConfiguratorBasicStruct $productConfigurator) use ($id) {
            return $productConfigurator->getProductId() === $id;
        });
    }

    public function getConfigurationOptionIds(): array
    {
        return $this->fmap(function(ProductConfiguratorBasicStruct $productConfigurator) {
            return $productConfigurator->getConfigurationOptionId();
        });
    }

    public function filterByConfigurationOptionId(string $id): ProductConfiguratorBasicCollection
    {
        return $this->filter(function(ProductConfiguratorBasicStruct $productConfigurator) use ($id) {
            return $productConfigurator->getConfigurationOptionId() === $id;
        });
    }

    public function getProductVersionIds(): array
    {
        return $this->fmap(function(ProductConfiguratorBasicStruct $productConfigurator) {
            return $productConfigurator->getProductVersionId();
        });
    }

    public function filterByProductVersionId(string $id): ProductConfiguratorBasicCollection
    {
        return $this->filter(function(ProductConfiguratorBasicStruct $productConfigurator) use ($id) {
            return $productConfigurator->getProductVersionId() === $id;
        });
    }

    public function getConfigurationOptionVersionIds(): array
    {
        return $this->fmap(function(ProductConfiguratorBasicStruct $productConfigurator) {
            return $productConfigurator->getConfigurationOptionVersionId();
        });
    }

    public function filterByConfigurationOptionVersionId(string $id): ProductConfiguratorBasicCollection
    {
        return $this->filter(function(ProductConfiguratorBasicStruct $productConfigurator) use ($id) {
            return $productConfigurator->getConfigurationOptionVersionId() === $id;
        });
    }

    public function getConfigurationOptions(): ConfigurationGroupOptionBasicCollection
    {
        return new ConfigurationGroupOptionBasicCollection(
            $this->fmap(function(ProductConfiguratorBasicStruct $productConfigurator) {
                return $productConfigurator->getConfigurationOption();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductConfiguratorBasicStruct::class;
    }
}