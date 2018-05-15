<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\System\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
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

    public function getOptionIds(): array
    {
        return $this->fmap(function (ProductServiceBasicStruct $productService) {
            return $productService->getOptionId();
        });
    }

    public function filterByGroupId(string $groupId): self
    {
        return $this->filter(function (ProductServiceBasicStruct $service) use ($groupId) {
            return $service->getOption()->getGroupId() === $groupId;
        });
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(function (ProductServiceBasicStruct $productService) use ($id) {
            return $productService->getOptionId() === $id;
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

    public function getOptions(): ConfigurationGroupOptionBasicCollection
    {
        return new ConfigurationGroupOptionBasicCollection(
            $this->fmap(function (ProductServiceBasicStruct $productService) {
                return $productService->getOption();
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
