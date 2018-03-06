<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductServiceBasicStruct;
use Shopware\Api\Tax\Collection\TaxBasicCollection;

class ProductServiceBasicCollection extends EntityCollection
{
    /**
     * @var ProductServiceBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductServiceBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductServiceBasicStruct
    {
        return parent::current();
    }

    public function getVersionIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getVersionId();
        });
    }

    public function filterByVersionId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getVersionId() === $id;
        });
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getProductId() === $id;
        });
    }

    public function getConfigurationOptionIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getConfigurationOptionId();
        });
    }

    public function filterByConfigurationOptionId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getConfigurationOptionId() === $id;
        });
    }

    public function getTaxIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getTaxId();
        });
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getTaxId() === $id;
        });
    }

    public function getProductVersionIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getProductVersionId();
        });
    }

    public function filterByProductVersionId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getProductVersionId() === $id;
        });
    }

    public function getConfigurationOptionVersionIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getConfigurationOptionVersionId();
        });
    }

    public function filterByConfigurationOptionVersionId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getConfigurationOptionVersionId() === $id;
        });
    }

    public function getTaxVersionIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getTaxVersionId();
        });
    }

    public function filterByTaxVersionId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getTaxVersionId() === $id;
        });
    }

    public function getConfigurationOptions(): ConfigurationGroupOptionBasicCollection
    {
        return new ConfigurationGroupOptionBasicCollection(
            $this->fmap(function (ProductServiceBasicStruct $productService) {
                return $productService->getConfigurationOption();
            })
        );
    }

    public function getTaxes(): TaxBasicCollection
    {
        return new TaxBasicCollection(
            $this->fmap(function (ProductServiceBasicStruct $productService) {
                return $productService->getTax();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductServiceBasicStruct::class;
    }
}
