<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule;

use Shopware\Core\Framework\Pricing\PriceRuleCollection;

class ProductPriceRuleCollection extends PriceRuleCollection
{
    /**
     * @var ProductPriceRuleEntity[]
     */
    protected $elements = [];

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
        foreach ($this->elements as $price) {
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
