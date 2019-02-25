<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule;

use Shopware\Core\Framework\Pricing\PriceRuleCollection;

/**
 * @method void                        add(ProductPriceRuleEntity $entity)
 * @method void                        set(string $key, ProductPriceRuleEntity $entity)
 * @method ProductPriceRuleEntity[]    getIterator()
 * @method ProductPriceRuleEntity[]    getElements()
 * @method ProductPriceRuleEntity|null get(string $key)
 * @method ProductPriceRuleEntity|null first()
 * @method ProductPriceRuleEntity|null last()
 */
class ProductPriceRuleCollection extends PriceRuleCollection
{
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductPriceRuleEntity $price) {
            return $price->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductPriceRuleEntity $price) use ($id) {
            return $price->getProductId() === $id;
        });
    }

    public function sortByQuantity(): void
    {
        $this->sort(function (ProductPriceRuleEntity $a, ProductPriceRuleEntity $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });
    }

    public function getQuantityPrice(int $quantity): ProductPriceRuleEntity
    {
        foreach ($this->getIterator() as $price) {
            $end = $price->getQuantityEnd() ?? $quantity + 1;

            if ($price->getQuantityStart() <= $quantity && $end >= $quantity) {
                return $price;
            }
        }

        throw new \RuntimeException(sprintf('CalculatedPrice for quantity %s not found', $quantity));
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceRuleEntity::class;
    }
}
