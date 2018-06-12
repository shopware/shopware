<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule;

use Shopware\Core\Framework\Pricing\PriceRuleCollection;

class ProductPriceRuleCollection extends PriceRuleCollection
{
    /**
     * @var ProductPriceRuleStruct[]
     */
    protected $elements = [];

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductPriceRuleStruct $price) {
            return $price->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductPriceRuleStruct $price) use ($id) {
            return $price->getProductId() === $id;
        });
    }

    public function sortByQuantity()
    {
        $this->sort(function (ProductPriceRuleStruct $a, ProductPriceRuleStruct $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });
    }

    public function getQuantityPrice(int $quantity): ProductPriceRuleStruct
    {
        foreach ($this->elements as $price) {
            $end = $price->getQuantityEnd() ?? $quantity + 1;

            if ($price->getQuantityStart() <= $quantity && $end >= $quantity) {
                return $price;
            }
        }

        throw new \RuntimeException(sprintf('Price for quantity %s not found', $quantity));
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceRuleStruct::class;
    }
}
