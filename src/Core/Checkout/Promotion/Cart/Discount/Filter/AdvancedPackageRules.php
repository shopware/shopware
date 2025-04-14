<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AdvancedPackageRules extends SetGroupScopeFilter
{
    public function getDecorated(): SetGroupScopeFilter
    {
        throw new DecorationPatternException(self::class);
    }

    public function filter(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountPackageCollection
    {
        $priceDefinition = $discount->getPriceDefinition();

        foreach ($packages as $packageKey => $package) {
            $checkedItems = [];

            foreach ($package->getMetaData() as $key => $item) {
                if (!\array_key_exists($item->getLineItemId(), $checkedItems)) {
                    $lineItem = $package->getCartItem($item->getLineItemId());

                    $checkedItems[$item->getLineItemId()] = $this->isRulesFilterValid($lineItem, $priceDefinition, $context);
                }

                if (!$checkedItems[$item->getLineItemId()]) {
                    $package->getMetaData()->remove($key);
                    $package->getCartItems()->remove($key);
                }
            }

            if ($package->getMetaData()->count() === 0) {
                $packages->remove($packageKey);
            }
        }

        return $packages;
    }

    private function isRulesFilterValid(LineItem $item, PriceDefinitionInterface $priceDefinition, SalesChannelContext $context): bool
    {
        // if the price definition doesnt allow filters,
        // then return valid for the item
        if (!method_exists($priceDefinition, 'getFilter')) {
            return true;
        }

        /** @var Rule|null $filter */
        $filter = $priceDefinition->getFilter();

        // if the definition exists, but is empty
        // this means we have no restrictions and thus its valid
        if (!$filter instanceof Rule) {
            return true;
        }

        // if our price definition has a filter rule
        // then extract it, and check if it matches
        $scope = new LineItemScope($item, $context);

        if ($filter->match($scope)) {
            return true;
        }

        return false;
    }
}
