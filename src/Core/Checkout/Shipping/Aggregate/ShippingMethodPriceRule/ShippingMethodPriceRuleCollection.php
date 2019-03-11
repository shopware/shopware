<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPriceRule;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(ShippingMethodPriceRuleEntity $entity)
 * @method void                               set(string $key, ShippingMethodPriceRuleEntity $entity)
 * @method ShippingMethodPriceRuleEntity[]    getIterator()
 * @method ShippingMethodPriceRuleEntity[]    getElements()
 * @method ShippingMethodPriceRuleEntity|null get(string $key)
 * @method ShippingMethodPriceRuleEntity|null first()
 * @method ShippingMethodPriceRuleEntity|null last()
 */
class ShippingMethodPriceRuleCollection extends EntityCollection
{
    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ShippingMethodPriceRuleEntity $shippingMethodPrice) {
            return $shippingMethodPrice->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (ShippingMethodPriceRuleEntity $shippingMethodPrice) use ($id) {
            return $shippingMethodPrice->getShippingMethodId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodPriceRuleEntity::class;
    }
}
