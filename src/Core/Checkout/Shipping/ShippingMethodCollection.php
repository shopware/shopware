<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPriceRule\ShippingMethodPriceRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(ShippingMethodEntity $entity)
 * @method void                      set(string $key, ShippingMethodEntity $entity)
 * @method ShippingMethodEntity[]    getIterator()
 * @method ShippingMethodEntity[]    getElements()
 * @method ShippingMethodEntity|null get(string $key)
 * @method ShippingMethodEntity|null first()
 * @method ShippingMethodEntity|null last()
 */
class ShippingMethodCollection extends EntityCollection
{
    public function filterByActiveRules(CheckoutContext $checkoutContext): ShippingMethodCollection
    {
        return $this->filter(
            function (ShippingMethodEntity $shippingMethod) use ($checkoutContext) {
                $matches = array_intersect($shippingMethod->getAvailabilityRuleIds(), $checkoutContext->getRuleIds());

                return !empty($matches);
            }
        );
    }

    public function getPriceIds(): array
    {
        $ids = [[]];

        foreach ($this->getIterator() as $element) {
            $ids[] = $element->getPriceRules()->getIds();
        }

        return array_merge(...$ids);
    }

    public function getPriceRules(): ShippingMethodPriceRuleCollection
    {
        $prices = [[]];

        foreach ($this->getIterator() as $element) {
            $prices[] = $element->getPriceRules();
        }

        $prices = array_merge(...$prices);

        return new ShippingMethodPriceRuleCollection($prices);
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodEntity::class;
    }
}
