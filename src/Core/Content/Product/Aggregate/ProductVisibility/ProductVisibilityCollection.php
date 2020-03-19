<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductVisibility;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductVisibilityCollection extends EntityCollection
{
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductVisibilityEntity $productService) {
            return $productService->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductVisibilityEntity $productService) use ($id) {
            return $productService->getProductId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'product_visibility_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductVisibilityEntity::class;
    }
}
