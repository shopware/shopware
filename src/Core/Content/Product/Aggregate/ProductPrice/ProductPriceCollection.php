<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPrice;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceRuleCollection;

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
    public function getApiAlias(): string
    {
        return 'product_price_collection';
    }

    public function filterByRuleId(string $ruleId): self
    {
        return $this->filter(function (ProductPriceEntity $price) use ($ruleId) {
            return $ruleId === $price->getRuleId();
        });
    }

    public function sortByQuantity(): void
    {
        $this->sort(function (ProductPriceEntity $a, ProductPriceEntity $b) {
            return $a->getQuantityStart() <=> $b->getQuantityStart();
        });
    }

    public function sortByPrice(Context $context): void
    {
        $this->sort(function (ProductPriceEntity $a, ProductPriceEntity $b) use ($context) {
            if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
                return $a->getPrice()->first()->getGross() <=> $b->getPrice()->first()->getGross();
            }

            return $a->getPrice()->first()->getNet() <=> $b->getPrice()->first()->getNet();
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceEntity::class;
    }
}
