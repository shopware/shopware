<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPrice;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductPriceEntity>
 */
#[Package('inventory')]
class ProductPriceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_price_collection';
    }

    public function filterByRuleId(string $ruleId): self
    {
        return $this->filter(fn (ProductPriceEntity $price) => $ruleId === $price->getRuleId());
    }

    public function sortByQuantity(): void
    {
        $this->sort(fn (ProductPriceEntity $a, ProductPriceEntity $b) => $a->getQuantityStart() <=> $b->getQuantityStart());
    }

    public function sortByPrice(Context $context): void
    {
        $this->sort(function (ProductPriceEntity $a, ProductPriceEntity $b) use ($context) {
            $a = $a->getPrice()->first();
            $b = $b->getPrice()->first();

            if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
                return ($a ? $a->getGross() : 0) <=> ($b ? $b->getGross() : 0);
            }

            return ($a ? $a->getNet() : 0) <=> ($b ? $b->getNet() : 0);
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceEntity::class;
    }
}
