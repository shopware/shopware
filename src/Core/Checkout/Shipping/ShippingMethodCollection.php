<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends EntityCollection<ShippingMethodEntity>
 */
#[Package('checkout')]
class ShippingMethodCollection extends EntityCollection
{
    public function filterByActiveRules(SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        return $this->filter(
            fn (ShippingMethodEntity $shippingMethod) => \in_array($shippingMethod->getAvailabilityRuleId(), $salesChannelContext->getRuleIds(), true)
        );
    }

    /**
     * @return list<string>
     */
    public function getPriceIds(): array
    {
        $ids = [[]];

        foreach ($this->getIterator() as $element) {
            $ids[] = $element->getPrices()->getIds();
        }

        return array_merge(...$ids);
    }

    public function getPrices(): ShippingMethodPriceCollection
    {
        $prices = [[]];

        foreach ($this->getIterator() as $element) {
            $prices[] = $element->getPrices()->getElements();
        }

        $prices = array_merge(...$prices);

        return new ShippingMethodPriceCollection($prices);
    }

    /**
     * Sorts the selected shipping method first
     * If a different default shipping method is defined, it will be sorted second
     * All other shipping methods keep their respective sorting
     */
    public function sortShippingMethodsByPreference(SalesChannelContext $context): void
    {
        $ids = array_merge(
            [$context->getShippingMethod()->getId(), $context->getSalesChannel()->getShippingMethodId()],
            $this->getIds()
        );

        $this->sortByIdArray($ids);
    }

    public function getApiAlias(): string
    {
        return 'shipping_method_collection';
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodEntity::class;
    }
}
