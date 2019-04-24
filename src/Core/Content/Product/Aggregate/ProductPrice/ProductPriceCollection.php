<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPrice;

use Shopware\Core\Framework\Pricing\PriceRuleCollection;

/**
 * @method void                    add(ProductPriceEntity $entity)
 * @method void                    set(string $key, ProductPriceEntity $entity)
 * @method ProductPriceEntity[]    getIterator()
 * @method ProductPriceEntity[]    getElements()
 * @method ProductPriceEntity|null get(string $key)
 * @method ProductPriceEntity|null first()
 * @method ProductPriceEntity|null last()
 */
class ProductPriceCollection extends PriceRuleCollection
{
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductPriceEntity $price) {
            return $price->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductPriceEntity $price) use ($id) {
            return $price->getProductId() === $id;
        });
    }

    public function sortByQuantity(): void
    {
        $this->sort(function (ProductPriceEntity $a, ProductPriceEntity $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });
    }

    public function getQuantityPrices(int $quantity): self
    {
        return $this->filter(function (ProductPriceEntity $price) use ($quantity) {
            $end = $price->getQuantityEnd() ?? $quantity + 1;

            return $price->getQuantityStart() <= $quantity && $end >= $quantity;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceEntity::class;
    }
}
