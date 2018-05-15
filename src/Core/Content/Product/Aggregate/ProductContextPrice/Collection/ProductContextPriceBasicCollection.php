<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductContextPrice\Collection;

use Shopware\Content\Product\Aggregate\ProductContextPrice\Struct\ProductContextPriceBasicStruct;

class ProductContextPriceBasicCollection extends \Shopware\Application\Context\Collection\ContextPriceCollection
{
    /**
     * @var ProductContextPriceBasicStruct[]
     */
    protected $elements = [];

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductContextPriceBasicStruct $price) {
            return $price->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductContextPriceBasicStruct $price) use ($id) {
            return $price->getProductId() === $id;
        });
    }

    public function sortByQuantity()
    {
        $this->sort(function (ProductContextPriceBasicStruct $a, ProductContextPriceBasicStruct $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });
    }

    public function getQuantityPrice(int $quantity): ProductContextPriceBasicStruct
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
        return ProductContextPriceBasicStruct::class;
    }
}
