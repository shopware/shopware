<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Collection;

use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Struct\ProductPriceRuleBasicStruct;

class ProductPriceRuleBasicCollection extends \Shopware\Core\Framework\Pricing\PriceRuleCollection
{
    /**
     * @var ProductPriceRuleBasicStruct[]
     */
    protected $elements = [];

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductPriceRuleBasicStruct $price) {
            return $price->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductPriceRuleBasicStruct $price) use ($id) {
            return $price->getProductId() === $id;
        });
    }

    public function sortByQuantity()
    {
        $this->sort(function (ProductPriceRuleBasicStruct $a, ProductPriceRuleBasicStruct $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });
    }

    public function getQuantityPrice(int $quantity): ProductPriceRuleBasicStruct
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
        return ProductPriceRuleBasicStruct::class;
    }
}
