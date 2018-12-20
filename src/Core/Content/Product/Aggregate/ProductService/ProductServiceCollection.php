<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Tax\TaxCollection;

class ProductServiceCollection extends EntityCollection
{
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductServiceEntity $productService) {
            return $productService->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductServiceEntity $productService) use ($id) {
            return $productService->getProductId() === $id;
        });
    }

    public function getOptionIds(): array
    {
        return $this->fmap(function (ProductServiceEntity $productService) {
            return $productService->getOptionId();
        });
    }

    public function filterByGroupId(string $groupId): self
    {
        return $this->filter(function (ProductServiceEntity $service) use ($groupId) {
            return $service->getOption()->getGroupId() === $groupId;
        });
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(function (ProductServiceEntity $productService) use ($id) {
            return $productService->getOptionId() === $id;
        });
    }

    public function getTaxIds(): array
    {
        return $this->fmap(function (ProductServiceEntity $productService) {
            return $productService->getTaxId();
        });
    }

    public function filterByTaxId(string $id): self
    {
        return $this->filter(function (ProductServiceEntity $productService) use ($id) {
            return $productService->getTaxId() === $id;
        });
    }

    public function getOptions(): ConfigurationGroupOptionCollection
    {
        return new ConfigurationGroupOptionCollection(
            $this->fmap(function (ProductServiceEntity $productService) {
                return $productService->getOption();
            })
        );
    }

    public function getTaxes(): TaxCollection
    {
        return new TaxCollection(
            $this->fmap(function (ProductServiceEntity $productService) {
                return $productService->getTax();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductServiceEntity::class;
    }
}
